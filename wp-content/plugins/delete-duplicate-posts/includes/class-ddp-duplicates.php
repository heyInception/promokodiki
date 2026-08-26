<?php

/**
 * Duplicate detection and deletion.
 *
 * @package DeleteDuplicatePosts
 */
namespace DeleteDuplicatePosts;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class DDP_Duplicates {
    /**
     * Plain-text alternative body for the status email.
     *
     * Held briefly so the phpmailer_init callback can attach it as AltBody,
     * turning the message into a multipart/alternative email.
     *
     * @var string
     */
    private static $status_email_text = '';

    /**
     * delete_duplicates_ajax.
     *
     * @author  Lars Koudal
     * @author  Unknown
     * @since   v0.0.1
     * @version v1.0.0  Tuesday, January 12th, 2021.
     * @version v1.0.1  Tuesday, October 31st, 2023.
     * @version v1.0.2  Wednesday, November 1st, 2023.
     * @version v1.0.3  Tuesday, April 2nd, 2024.
     * @version v1.0.4  Tuesday, May 7th, 2024.
     * @access  public static
     * @param   boolean $return_data    Default: false
     * @return  mixed
     */
    public static function delete_duplicates_ajax( $return_data = false ) {
        // Check user permissions
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'delete-duplicate-posts' ) );
            return;
        }
        // Verify the AJAX request, to prevent processing requests external of the site.
        check_ajax_referer( 'cp_ddp_delete_loglines' );
        // Log the cleaning action
        DDP_Logger::log( __( 'Cleaning duplicates', 'delete-duplicate-posts' ) );
        // Validate the POST data
        $checked_posts = $_POST['checked_posts'] ?? null;
        if ( empty( $checked_posts ) || !is_array( $checked_posts ) ) {
            wp_send_json_error( __( 'No duplicates were selected?', 'delete-duplicate-posts' ) );
            return;
        }
        // Process and sanitize the checked posts
        $cleaned_posts = array();
        foreach ( $checked_posts as $cp ) {
            if ( !empty( $cp['ID'] ) && !empty( $cp['orgID'] ) && is_numeric( $cp['ID'] ) && is_numeric( $cp['orgID'] ) ) {
                $cleaned_posts[] = array(
                    'ID'    => intval( $cp['ID'] ),
                    'orgID' => intval( $cp['orgID'] ),
                );
            }
        }
        // Check if any valid posts were found
        if ( empty( $cleaned_posts ) ) {
            wp_send_json_error( __( 'Invalid duplicates selected.', 'delete-duplicate-posts' ) );
            return;
        }
        // Attempt to clean duplicates and handle possible failures
        $result = DDP_Duplicates::cleandupes( true, $cleaned_posts );
        if ( wp_doing_ajax() ) {
            wp_send_json_success( $result );
        }
        if ( $return_data ) {
            return $result;
        }
    }

    /**
     * return_duplicates_ajax.
     *
     * @author  Lars Koudal
     * @author  Unknown
     * @since   v0.0.1
     * @version v1.0.0  Tuesday, January 12th, 2021.
     * @version v1.0.1  Wednesday, November 1st, 2023.
     * @access  public static
     * @return  void
     */
    public static function return_duplicates_ajax() {
        check_ajax_referer( 'cp_ddp_return_duplicates' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'delete-duplicate-posts' ) );
            return;
        }
        // Get duplicates
        $duplicates = DDP_Duplicates::return_duplicates( true );
        // Initialize DataTables response array
        $response = array(
            'draw'            => ( isset( $_POST['draw'] ) ? intval( $_POST['draw'] ) : 0 ),
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => array(),
        );
        if ( !empty( $duplicates ) && isset( $duplicates['dupes'] ) ) {
            $response['recordsTotal'] = $duplicates['dupescount'];
            $response['recordsFiltered'] = $duplicates['dupescount'];
            foreach ( $duplicates['dupes'] as $dupe ) {
                $title = ( '' === $dupe['title'] ? '-empty-' : $dupe['title'] );
                $orgTitle = ( '' === $dupe['orgtitle'] ? '-empty-' : $dupe['orgtitle'] );
                $permalink = esc_url( get_permalink( $dupe['ID'] ) );
                $orgPermalink = esc_url( get_permalink( $dupe['orgID'] ) );
                // When the match is based on a field that is not already visible
                // (excerpt, meta value), show the shared value in BOTH columns so the
                // comparison can be verified at a glance.
                $match_snippet = '';
                if ( isset( $dupe['matchvalue'] ) && '' !== trim( (string) $dupe['matchvalue'] ) ) {
                    $match_label = ( isset( $dupe['matchlabel'] ) ? $dupe['matchlabel'] : __( 'Matched value', 'delete-duplicate-posts' ) );
                    $match_snippet = sprintf( '<div class="ddp-matchvalue"><span class="ddp-matchlabel">%s:</span> <em>%s</em></div>', esc_html( $match_label ), esc_html( self::truncate_match_value( (string) $dupe['matchvalue'] ) ) );
                }
                // Type + status are shown on hover to keep the row readable.
                /* translators: 1: Post type, 2: Post status. */
                $type_status = sprintf( __( 'Type: %1$s · Status: %2$s', 'delete-duplicate-posts' ), $dupe['type'], $dupe['status'] );
                $response['data'][] = array(
                    'ID'        => esc_html( $dupe['ID'] ),
                    'orgID'     => esc_html( $dupe['orgID'] ),
                    'duplicate' => sprintf(
                        '<a href="%s" target="_blank" title="%s">%s</a> <span class="ddp-id">#%s</span><div class="ddp-why">%s</div>%s',
                        esc_url( $permalink ),
                        esc_attr( $type_status ),
                        esc_html( $title ),
                        esc_html( $dupe['ID'] ),
                        esc_html( $dupe['why'] ),
                        $match_snippet
                    ),
                    'original'  => sprintf(
                        '<a href="%s" target="_blank">%s</a> <span class="ddp-id">#%s</span>%s',
                        esc_url( $orgPermalink ),
                        esc_html( $orgTitle ),
                        esc_html( $dupe['orgID'] ),
                        $match_snippet
                    ),
                );
            }
        }
        wp_send_json( $response );
        exit;
    }

    /**
     * Shorten a matched value (excerpt / meta value) for display in the results table.
     *
     * Collapses runs of whitespace and clips to a readable length so long excerpts or
     * meta values do not blow out the table layout.
     *
     * @param  string $value  The full matched value.
     * @param  int    $length Maximum characters to show. Default 160.
     * @return string Trimmed, single-line value with an ellipsis when clipped.
     */
    public static function truncate_match_value( $value, $length = 160 ) {
        $value = trim( preg_replace( '/\\s+/', ' ', (string) $value ) );
        if ( function_exists( 'mb_strlen' ) ) {
            if ( mb_strlen( $value ) > $length ) {
                return mb_substr( $value, 0, $length ) . '…';
            }
            return $value;
        }
        if ( strlen( $value ) > $length ) {
            return substr( $value, 0, $length ) . '…';
        }
        return $value;
    }

    /**
     * Converts a number to relevant unit size
     *
     * @author   Lars Koudal
     * @since    v0.0.1
     * @version  v1.0.0  Thursday, June 24th, 2021.
     * @param    mixed   $size
     * @return   string
     */
    public static function pretty_value( $size ) {
        if ( $size <= 0 || !is_numeric( $size ) ) {
            return '0 b';
        }
        $unit = array(
            'b',
            'kb',
            'mb',
            'gb',
            'tb',
            'pb'
        );
        $log = log( $size, 1024 );
        $i = floor( $log );
        $i = max( 0, min( (int) $i, count( $unit ) - 1 ) );
        $num = $size / pow( 1024, $i );
        $calc = round( $num, 2 ) . ' ' . $unit[$i];
        return $calc;
    }

    /**
     * Returns duplicates based on current settings - internal, not used via AJAX
     *
     * @author  Lars Koudal
     * @author  Unknown
     * @since   v0.0.1
     * @version v1.0.0  Tuesday, January 12th, 2021.
     * @version v1.0.1  Wednesday, November 1st, 2023.
     * @access  public static
     * @param   boolean $return Default: false
     * @return  void
     */
    public static function return_duplicates( $return = false ) {
        DDP_Logger::timerstart( 'return_duplicates' );
        $options = DDP_Settings::get_options();
        $comparemethod = 'titlecompare';
        $return_duplicates_time = false;
        global $ddp_fs;
        $json_response = array();
        // @ check compare method - maybe change lookup routine?
        global $wpdb;
        $table_name = $wpdb->prefix . 'posts';
        $resultslimit = $options['ddp_resultslimit'];
        $viewlimit = intval( $resultslimit );
        if ( 0 === $viewlimit ) {
            $viewlimit = 9999;
        }
        $ddp_pts_arr = $options['ddp_pts'];
        if ( !isset( $ddp_pts_arr ) || !is_array( $ddp_pts_arr ) ) {
            $ddp_pts_arr = array();
        }
        $post_stati_arr = array('publish');
        // Attachments use post_status "inherit", which is not exposed in the status UI.
        if ( in_array( 'attachment', $ddp_pts_arr, true ) && !in_array( 'inherit', $post_stati_arr, true ) ) {
            $post_stati_arr[] = 'inherit';
        }
        $escaped_post_stati = implode( ', ', array_map( function ( $status ) {
            return "'" . esc_sql( $status ) . "'";
        }, $post_stati_arr ) );
        $escaped_ddp_pts = implode( ', ', array_map( function ( $type ) {
            return "'" . esc_sql( $type ) . "'";
        }, $ddp_pts_arr ) );
        $ddp_pts = $escaped_ddp_pts;
        $post_stati = $escaped_post_stati;
        $order = $options['ddp_keep'];
        // verify default value has been set
        if ( 'oldest' !== $order ) {
            // two choices, if its not the first its the second...
            $options['ddp_keep'] = 'latest';
            $order = 'latest';
        }
        if ( 'oldest' === $order ) {
            $minmax = 'MIN(id)';
        }
        if ( 'latest' === $order ) {
            $minmax = 'MAX(id)';
        }
        $ddpstatuscnt = array();
        $dupescount = 0;
        if ( '' !== $ddp_pts ) {
            $thisquery = false;
            // **** Compare by title ****
            if ( 'titlecompare' === $comparemethod ) {
                $limit = ( isset( $_POST['length'] ) ? intval( $_POST['length'] ) : 10 );
                // Default to 10 if not set
                $offset = ( isset( $_POST['start'] ) ? intval( $_POST['start'] ) : 0 );
                // Default to 0 if not set
                if ( !wp_doing_ajax() && $return ) {
                    $limit = $viewlimit;
                    // Cron job: "No limit" (0) must not become LIMIT 0.
                }
                $wpdb->query( 'SET SQL_BIG_SELECTS=1' );
                $resultsoutput = ' ORDER BY ID LIMIT ' . intval( $limit ) . ' OFFSET ' . intval( $offset );
                if ( $options['ddp_debug'] ) {
                    DDP_Logger::log( 'DEBUG: SQL - Setting SET SQL_BIG_SELECTS=1' );
                }
                $thisquery = "SELECT * FROM (\n\t\t\t\t\t\t\t\t\t\t\t\tSELECT t1.ID, t1.post_title, t1.post_type, t1.post_status, save_this_post_id \n\t\t\t\t\t\t\t\t\t\t\t\tFROM {$table_name} AS t1 \n\t\t\t\t\t\t\t\t\t\t\t\tINNER JOIN ( \n\t\t\t\t\t\t\t\t\t\t\t\t\tSELECT post_title, {$minmax} AS save_this_post_id \n\t\t\t\t\t\t\t\t\t\t\t\t\tFROM {$table_name} \n\t\t\t\t\t\t\t\t\t\t\t\t\tWHERE post_type IN ( {$ddp_pts} ) \n\t\t\t\t\t\t\t\t\t\t\t\t\tAND post_type NOT IN ('nav_menu_item') \n\t\t\t\t\t\t\t\t\t\t\t\t\tAND post_status IN ( {$post_stati} ) \n\t\t\t\t\t\t\t\t\t\t\t\t\tGROUP BY post_title \n\t\t\t\t\t\t\t\t\t\t\t\t\tHAVING COUNT(*) > 1 \n\t\t\t\t\t\t\t\t\t\t\t\t\t) AS t2 ON t1.post_title = t2.post_title \n\t\t\t\t\t\t\t\t\t\t\t\t\tWHERE t1.post_status IN ( {$post_stati} )\n\t\t\t\t\t\t\t\t\t\t\t\t\tAND t1.post_type NOT IN ('nav_menu_item')\n\t\t\t\t\t\t\t\t\t\t\t\t\tORDER BY t1.post_title, t1.post_date DESC\n\t\t\t\t\t\t\t\t\t\t\t\t\t) AS derived_table\n\t\t\t\t\t\t\t\t\t\t\t\t\tWHERE ID != save_this_post_id\n\t\t\t\t\t\t\t\t\t\t\t\t\t{$resultsoutput}";
                if ( $options['ddp_debug'] ) {
                    DDP_Logger::log( 'DEBUG: SQL ' . esc_attr( $thisquery ) );
                }
                $json_response['lookup_query'] = $thisquery;
                $dupes = $wpdb->get_results( $thisquery, ARRAY_A );
                // here we get total dupes - not cute, but the other approach not working.
                $total_dupes_query = "SELECT COUNT(*) FROM (\n\t\t\t\t\t\t\t\t\t\t\t\t\t\tSELECT t1.ID, t1.post_title, t1.post_type, t1.post_status, save_this_post_id \n\t\t\t\t\t\t\t\t\t\t\t\t\t\tFROM {$table_name} AS t1 \n\t\t\t\t\t\t\t\t\t\t\t\t\t\tINNER JOIN ( \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tSELECT post_title, {$minmax} AS save_this_post_id \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tFROM {$table_name} \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tWHERE post_type IN ( {$ddp_pts} ) \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tAND post_type NOT IN ('nav_menu_item') \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tAND post_status IN ( {$post_stati} ) \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tGROUP BY post_title \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tHAVING COUNT(*)>1 \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t) AS t2 \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tON t1.post_title = t2.post_title \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tWHERE t1.post_status IN ( {$post_stati} )\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tAND t1.post_type NOT IN ('nav_menu_item')\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t) AS derived_table\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tWHERE ID != save_this_post_id";
                $total_dupes = $wpdb->get_var( $total_dupes_query );
                if ( $options['ddp_debug'] ) {
                    DDP_Logger::log( 'DEBUG: SQL total_dupes_query ' . esc_attr( $total_dupes_query ) );
                }
                if ( '' !== $wpdb->last_error ) {
                    $last_error = htmlspecialchars( $wpdb->last_error, ENT_QUOTES );
                    $json_response['lookup_error'] = htmlspecialchars( $wpdb->last_error, ENT_QUOTES );
                    DDP_Logger::log( sprintf( 
                        /* translators: 1: Database error message, 2: SQL query */
                        __( 'Look up error: %1$s %2$s', 'delete-duplicate-posts' ),
                        $last_error,
                        $total_dupes_query
                     ) );
                }
                if ( $dupes ) {
                    $json_response['dupescount'] = $total_dupes;
                    foreach ( $dupes as $dupe ) {
                        $mystatus = $dupe['post_status'];
                        if ( isset( $ddpstatuscnt[$mystatus] ) ) {
                            $ddpstatuscnt[$mystatus] = $ddpstatuscnt[$mystatus] + 1;
                        } else {
                            $ddpstatuscnt[$mystatus] = 1;
                        }
                        // Only save the dupes
                        if ( $dupe['ID'] !== $dupe['save_this_post_id'] ) {
                            $dupedetails = array(
                                'ID'           => $dupe['ID'],
                                'permalink'    => get_permalink( $dupe['ID'] ),
                                'title'        => $dupe['post_title'],
                                'type'         => $dupe['post_type'],
                                'orgID'        => $dupe['save_this_post_id'],
                                'orgtitle'     => $dupe['post_title'],
                                'orgpermalink' => get_permalink( $dupe['save_this_post_id'] ),
                                'status'       => $dupe['post_status'],
                                'why'          => sprintf( __( 'Same title as post #%s', 'delete-duplicate-posts' ), $dupe['save_this_post_id'] ),
                            );
                            $json_response['dupes'][] = $dupedetails;
                        }
                    }
                }
            }
            $statusdata = '';
            if ( is_array( $ddpstatuscnt ) && count( $ddpstatuscnt ) > 1 ) {
                $statusdata .= '(';
                foreach ( $ddpstatuscnt as $key => $dsc ) {
                    $statusdata .= $key . ': ' . number_format_i18n( $dsc ) . ', ';
                }
                $statusdata = rtrim( $statusdata, ', ' );
                $statusdata .= ')';
            }
            $return_duplicates_time = DDP_Logger::timerstop( 'return_duplicates' );
            if ( $options['ddp_debug'] ) {
                $max = 5;
                if ( isset( $json_response['dupes'] ) ) {
                    $idlist = array();
                    $step = 0;
                    foreach ( $json_response['dupes'] as $dupe ) {
                        if ( $step <= $max ) {
                            $details = '';
                            if ( isset( $dupe['ID'] ) ) {
                                $details .= 'ID: ' . $dupe['ID'] . ' ';
                            }
                            if ( isset( $dupe['title'] ) ) {
                                $details .= ' title: "' . $dupe['title'] . '" ';
                            }
                            if ( isset( $dupe['permalink'] ) ) {
                                $details .= 'Permalink: ' . $dupe['permalink'] . ' ';
                            }
                            if ( isset( $dupe['status'] ) ) {
                                $details .= 'Status: ' . $dupe['status'] . ' ';
                            }
                            if ( isset( $dupe['type'] ) ) {
                                $details .= 'Type: ' . $dupe['type'] . ' ';
                            }
                            if ( isset( $dupe['orgID'] ) ) {
                                $details .= 'orgID: ' . $dupe['orgID'] . ' ';
                            }
                            if ( isset( $dupe['orgtitle'] ) ) {
                                $details .= 'orgtitle: ' . $dupe['orgtitle'] . ' ';
                            }
                            if ( isset( $dupe['orgpermalink'] ) ) {
                                $details .= ' orgpermalink: ' . $dupe['orgpermalink'] . ' ';
                            }
                            DDP_Logger::log( $details );
                        }
                        ++$step;
                    }
                }
            }
            if ( isset( $json_response['dupes'] ) ) {
                DDP_Logger::log( sprintf(
                    /* translators: 1: Number of duplicates, 2: Time in seconds, 3: Status data, 4: Memory usage */
                    __( 'Duplicates found: %1$d, Time: %2$s sec. %3$s Mem usage: %4$s', 'delete-duplicate-posts' ),
                    count( $json_response['dupes'] ),
                    $return_duplicates_time,
                    $statusdata,
                    DDP_Duplicates::pretty_value( memory_get_peak_usage( true ) )
                ) );
            }
        } else {
            $json_response['msg'] = __( 'Error: Choose post types to check.', 'delete-duplicate-posts' );
            $return_duplicates_time = DDP_Logger::timerstop( 'return_duplicates' );
            $json_response['time'] = $return_duplicates_time . ' sec';
            if ( $return ) {
                return $json_response;
            }
            wp_send_json_error( $json_response );
        }
        if ( !$return_duplicates_time ) {
            $return_duplicates_time = DDP_Logger::timerstop( 'return_duplicates' );
        }
        if ( isset( $json_response['dupescount'] ) ) {
            $json_response['msg'] = sprintf( 
                /* translators: 1: Number of duplicates found, 2: Time in seconds. */
                __( 'Duplicates found: %1$s. Time: %2$s sec.', 'delete-duplicate-posts' ),
                number_format_i18n( $json_response['dupescount'] ),
                esc_html( $return_duplicates_time )
             );
        }
        if ( $return ) {
            return $json_response;
        }
        wp_send_json_success( $json_response );
    }

    /**
     * Clean duplicates - not AJAX version
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Thursday, June 9th, 2022.
     * @access  public static
     * @param   boolean $manualrun  Default: false
     * @param   mixed   $to_delete  Default: array()
     * @return  array
     */
    public static function cleandupes( $manualrun = false, $to_delete = array() ) {
        global $wpdb, $ddp_fs;
        DDP_Logger::timerstart( 'ddp_totaltime' );
        // start total timer
        $options = DDP_Settings::get_options();
        $options['ddp_running'] = true;
        DDP_Settings::save_options( $options );
        if ( !$manualrun ) {
            DDP_Logger::log( __( 'Automatic CRON job running.', 'delete-duplicate-posts' ) );
        } else {
            DDP_Logger::log( __( 'Manually cleaning.', 'delete-duplicate-posts' ) );
        }
        // what to do with a manual run - no notices
        if ( count( $to_delete ) > 0 ) {
            $lookup_arr = array();
            foreach ( $to_delete as $td ) {
                $new_item = array();
                $new_item['ID'] = $td['ID'];
                $new_item['orgID'] = $td['orgID'];
                $new_item['type'] = get_post_type( $td['ID'] );
                $new_item['title'] = get_the_title( $td['ID'] );
                $lookup_arr['dupes'][] = $new_item;
            }
            $dupes = $lookup_arr;
        } else {
            $dupes = DDP_Duplicates::return_duplicates( true );
        }
        $resultnote = '';
        $dispcount = 0;
        $type_counts = array();
        if ( isset( $dupes['dupes'] ) ) {
            foreach ( $dupes['dupes'] as $dupe ) {
                $postid = $dupe['ID'];
                $title = substr( $dupe['title'], 0, 35 );
                if ( $postid ) {
                    DDP_Logger::timerstart( 'deletepost_' . $postid );
                    $delete_mode = 'trash';
                    $post_type = get_post_type( $postid );
                    $force_delete = 'permanent' === $delete_mode;
                    if ( 'attachment' === $post_type ) {
                        $deleteresult = wp_delete_attachment( $postid, $force_delete );
                    } elseif ( $force_delete ) {
                        $deleteresult = wp_delete_post( $postid, true );
                    } else {
                        $deleteresult = wp_trash_post( $postid );
                    }
                    $timespent = DDP_Logger::timerstop( 'deletepost_' . $postid );
                    ++$dispcount;
                    $count_key = ( '' !== $post_type ? $post_type : 'unknown' );
                    $type_counts[$count_key] = ( isset( $type_counts[$count_key] ) ? $type_counts[$count_key] + 1 : 1 );
                    $totaldeleted = get_option( 'ddp_deleted_duplicates' );
                    if ( false !== $totaldeleted ) {
                        ++$totaldeleted;
                        update_option( 'ddp_deleted_duplicates', $totaldeleted, false );
                    } else {
                        update_option( 'ddp_deleted_duplicates', 1, false );
                    }
                    if ( $options['ddp_debug'] ) {
                        // translators: Debug notice. 1: type of duplicate. 2: The title of the post. 3: The ID. 4: Time spent deleting.
                        DDP_Logger::log( sprintf(
                            __( 'DEBUG: Deleted %1$s %2$s (id: %3$s) in %4$s sec.', 'delete-duplicate-posts' ),
                            $dupe['type'],
                            $title,
                            $postid,
                            $timespent
                        ) );
                    }
                }
            }
        }
        $totaltimespent = DDP_Logger::timerstop( 'ddp_totaltime' );
        DDP_Logger::log( sprintf( 
            /* translators: 1: Number of deleted duplicate posts, 2: Time in seconds. */
            __( 'A total of %1$s duplicate posts were deleted in %2$s sec.', 'delete-duplicate-posts' ),
            $dispcount,
            $totaltimespent
         ) );
        $json_response = array(
            'totaltimespent' => $totaltimespent,
            'deleted'        => $dispcount,
        );
        // Mail logic...
        if ( 0 < $dispcount && $options['ddp_statusmail'] ) {
            $blogurl = esc_url( site_url() );
            $recipients = DDP_Settings::parse_email_recipients( $options['ddp_statusmail_recipient'] );
            $recipient_list = implode( ', ', $recipients );
            $email_data = self::gather_status_email_data(
                $dispcount,
                $blogurl,
                $type_counts,
                $manualrun,
                $options
            );
            $messagebody = self::build_status_email( $email_data );
            $mailstatus = false;
            if ( !empty( $recipients ) ) {
                $subject = self::build_status_email_subject( $email_data );
                $headers = array('Content-Type: text/html; charset=UTF-8');
                // Attach a plain-text alternative so clients without HTML get a clean version too.
                self::$status_email_text = self::build_status_email_text( $email_data );
                add_action( 'phpmailer_init', array(__CLASS__, 'add_plain_text_alt_body') );
                $mailstatus = wp_mail(
                    $recipients,
                    $subject,
                    $messagebody,
                    $headers
                );
                remove_action( 'phpmailer_init', array(__CLASS__, 'add_plain_text_alt_body') );
                self::$status_email_text = '';
                if ( $options['ddp_debug'] ) {
                    // translators: %s: Email recipient list.
                    DDP_Logger::log( sprintf( __( 'DEBUG: Sending email to: %s', 'delete-duplicate-posts' ), $recipient_list ) );
                }
                if ( $mailstatus ) {
                    // translators: %s: Email recipient list.
                    DDP_Logger::log( sprintf( __( 'Status email sent to %s.', 'delete-duplicate-posts' ), $recipient_list ) );
                }
            } else {
                // translators: %s: Email address field value.
                DDP_Logger::log( sprintf( __( 'Not a valid email %s.', 'delete-duplicate-posts' ), $options['ddp_statusmail_recipient'] ) );
            }
        }
        $options['ddp_running'] = false;
        DDP_Settings::save_options( $options );
        if ( !$manualrun && !wp_doing_ajax() ) {
            $json_response['msg'] = sprintf( 
                /* translators: %s: Number of duplicates deleted. */
                esc_html__( 'A total of %s duplicates were deleted.', 'delete-duplicate-posts' ),
                intval( $dispcount )
             );
        }
        return $json_response;
    }

    /**
     * Gather everything the status email needs into a single data array.
     *
     * @param int    $dispcount   Number of duplicates deleted in this run.
     * @param string $blogurl     Site URL (already escaped via esc_url()).
     * @param array  $type_counts Map of post type slug => number deleted.
     * @param bool   $manualrun   True when triggered manually, false for the cron job.
     * @param array  $options     Plugin options.
     * @return array Data consumed by the subject/HTML/text builders.
     */
    private static function gather_status_email_data(
        $dispcount,
        $blogurl,
        $type_counts,
        $manualrun,
        $options
    ) {
        $method = 'titlecompare';
        $details = array();
        // What was removed, broken down by post type.
        $removed_value = number_format_i18n( $dispcount );
        $type_parts = self::format_type_counts( ( is_array( $type_counts ) ? $type_counts : array() ) );
        if ( !empty( $type_parts ) ) {
            $removed_value .= ' (' . implode( ', ', $type_parts ) . ')';
        }
        $details[__( 'Removed', 'delete-duplicate-posts' )] = $removed_value;
        $details[__( 'Matched on', 'delete-duplicate-posts' )] = self::get_detection_method_label( $method, $options );
        $pts = ( isset( $options['ddp_pts'] ) && is_array( $options['ddp_pts'] ) ? $options['ddp_pts'] : array() );
        if ( !empty( $pts ) ) {
            $details[__( 'Post types scanned', 'delete-duplicate-posts' )] = implode( ', ', $pts );
        }
        $details[__( 'Kept', 'delete-duplicate-posts' )] = ( isset( $options['ddp_keep'] ) && 'latest' === $options['ddp_keep'] ? __( 'Newest copy', 'delete-duplicate-posts' ) : __( 'Oldest copy', 'delete-duplicate-posts' ) );
        $removal = __( 'Moved to Trash', 'delete-duplicate-posts' );
        $details[__( 'Removal', 'delete-duplicate-posts' )] = $removal;
        $details[__( 'Run type', 'delete-duplicate-posts' )] = ( $manualrun ? __( 'Manual run', 'delete-duplicate-posts' ) : __( 'Scheduled (cron) run', 'delete-duplicate-posts' ) );
        return array(
            'deleted'  => (int) $dispcount,
            'blogurl'  => $blogurl,
            'blogname' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
            'source'   => ( $manualrun ? 'manual' : 'cron' ),
            'details'  => $details,
        );
    }

    /**
     * Human-readable label for the detection method used.
     *
     * @param string $method  Detection method key.
     * @param array  $options Plugin options.
     * @return string Translated label describing why posts were matched.
     */
    private static function get_detection_method_label( $method, $options ) {
        if ( 'metacompare' === $method ) {
            $meta = ( isset( $options['ddp_compare_metatag'] ) ? $options['ddp_compare_metatag'] : '' );
            if ( '' !== $meta ) {
                // translators: %s: Custom field (meta) key.
                return sprintf( __( 'Identical custom field value (%s)', 'delete-duplicate-posts' ), $meta );
            }
            return __( 'Identical custom field value', 'delete-duplicate-posts' );
        }
        if ( 'excerptcompare' === $method ) {
            return __( 'Identical excerpt', 'delete-duplicate-posts' );
        }
        return __( 'Identical title', 'delete-duplicate-posts' );
    }

    /**
     * Format the per-post-type deletion counts into readable, pluralized parts.
     *
     * @param array $type_counts Map of post type slug => count.
     * @return array<int,string> e.g. array( '5 Posts', '3 Pages' ).
     */
    private static function format_type_counts( $type_counts ) {
        $parts = array();
        foreach ( $type_counts as $slug => $count ) {
            $label = $slug;
            $obj = get_post_type_object( $slug );
            if ( $obj && isset( $obj->labels ) ) {
                $label = ( 1 === (int) $count && !empty( $obj->labels->singular_name ) ? $obj->labels->singular_name : $obj->labels->name );
            }
            $parts[] = number_format_i18n( $count ) . ' ' . $label;
        }
        return $parts;
    }

    /**
     * Build the email subject line.
     *
     * @param array $data Data from gather_status_email_data().
     * @return string Subject line.
     */
    public static function build_status_email_subject( $data ) {
        $deleted = number_format_i18n( $data['deleted'] );
        $blogname = $data['blogname'];
        $source = ( 'manual' === $data['source'] ? __( 'manual run', 'delete-duplicate-posts' ) : __( 'scheduled run', 'delete-duplicate-posts' ) );
        return sprintf(
            // translators: 1: Number removed, 2: Blog name, 3: Run type.
            __( '[%2$s] %1$s duplicates removed (%3$s)', 'delete-duplicate-posts' ),
            $deleted,
            $blogname,
            $source
        );
    }

    /**
     * Build the HTML body for the duplicate-deletion status email.
     *
     * Uses table-based layout and inline styles so it renders consistently
     * across email clients.
     *
     * @param array $data Data from gather_status_email_data().
     * @return string Full HTML email body.
     */
    public static function build_status_email( $data ) {
        $blogname = $data['blogname'];
        $blogurl = $data['blogurl'];
        $deleted_num = number_format_i18n( $data['deleted'] );
        // translators: %s: Number of duplicate posts deleted in this run.
        $headline = sprintf( esc_html__( '%s duplicate posts removed', 'delete-duplicate-posts' ), '<span style="color:#2a7ae2;">' . esc_html( $deleted_num ) . '</span>' );
        $intro = sprintf( 
            // translators: %s: Blog name.
            esc_html__( "Hi Admin, here's a summary of the latest cleanup on %s.", 'delete-duplicate-posts' ),
            '<strong>' . esc_html( $blogname ) . '</strong>'
         );
        $site_button_label = esc_html__( 'View your site', 'delete-duplicate-posts' );
        $summary_heading = esc_html__( 'Run summary', 'delete-duplicate-posts' );
        $detail_rows = '';
        foreach ( $data['details'] as $label => $value ) {
            $detail_rows .= '<tr>' . '<td style="padding:7px 0;font-size:13px;line-height:1.5;color:#6b7280;width:42%;vertical-align:top;">' . esc_html( $label ) . '</td>' . '<td style="padding:7px 0;font-size:13px;line-height:1.5;color:#1f2937;font-weight:600;vertical-align:top;">' . esc_html( $value ) . '</td>' . '</tr>';
        }
        $product_rows = '';
        foreach ( self::get_status_email_products() as $product ) {
            $product_rows .= '<tr>' . '<td style="padding:12px 0;border-top:1px solid #eceef1;">' . '<a href="' . esc_url( $product['url'] ) . '" target="_blank" rel="noopener noreferrer" style="color:#2a7ae2;text-decoration:none;font-weight:600;font-size:15px;">' . esc_html( $product['name'] ) . '</a>' . '<div style="color:#5b6470;font-size:13px;line-height:1.5;margin-top:4px;">' . esc_html( $product['desc'] ) . '</div>' . '</td>' . '</tr>';
        }
        $from_the_maker = esc_html__( 'From the team behind Delete Duplicate Posts', 'delete-duplicate-posts' );
        $maker_intro = esc_html__( 'We build simple tools that keep WordPress sites clean, fast and secure. You might also like:', 'delete-duplicate-posts' );
        $why_receiving = sprintf( 
            // translators: %s: Linked plugin name "Delete Duplicate Posts".
            esc_html__( 'You are receiving this email because email notifications are enabled in %s.', 'delete-duplicate-posts' ),
            '<a href="https://cleverplugins.com/delete-duplicate-posts/" target="_blank" rel="noopener noreferrer" style="color:#2a7ae2;text-decoration:none;">' . esc_html__( 'Delete Duplicate Posts', 'delete-duplicate-posts' ) . '</a>'
         );
        $made_by = sprintf( 
            // translators: %s: Linked text "cleverplugins.com".
            esc_html__( 'Made with care by %s', 'delete-duplicate-posts' ),
            '<a href="https://cleverplugins.com" target="_blank" rel="noopener noreferrer" style="color:#2a7ae2;text-decoration:none;">' . esc_html__( 'cleverplugins.com', 'delete-duplicate-posts' ) . '</a>'
         );
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php 
        echo esc_html__( 'Deleted Duplicate Posts Status', 'delete-duplicate-posts' );
        ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;-webkit-font-smoothing:antialiased;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7;">
	<tr>
		<td align="center" style="padding:24px 12px;">
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e6e8eb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
				<tr>
					<td style="background-color:#1f2937;padding:22px 28px;">
						<span style="color:#ffffff;font-size:18px;font-weight:700;letter-spacing:.2px;">Delete Duplicate Posts</span>
					</td>
				</tr>
				<tr>
					<td style="padding:32px 28px 8px 28px;">
						<h1 style="margin:0 0 14px 0;font-size:24px;line-height:1.3;color:#1f2937;font-weight:700;"><?php 
        echo wp_kses_post( $headline );
        ?></h1>
						<p style="margin:0 0 20px 0;font-size:15px;line-height:1.6;color:#3b4452;"><?php 
        echo wp_kses_post( $intro );
        ?></p>
						<table role="presentation" cellpadding="0" cellspacing="0">
							<tr>
								<td style="border-radius:6px;background-color:#2a7ae2;">
									<a href="<?php 
        echo esc_url( $blogurl );
        ?>" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:11px 22px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;"><?php 
        echo esc_html( $site_button_label );
        ?></a>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td style="padding:20px 28px 8px 28px;">
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fb;border:1px solid #eceef1;border-radius:8px;">
							<tr>
								<td style="padding:16px 20px 4px 20px;">
									<p style="margin:0 0 8px 0;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9aa3af;"><?php 
        echo esc_html( $summary_heading );
        ?></p>
									<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
										<?php 
        echo wp_kses_post( $detail_rows );
        ?>
									</table>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td style="padding:20px 28px 8px 28px;">
						<div style="border-top:1px solid #eceef1;padding-top:24px;">
							<p style="margin:0 0 4px 0;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9aa3af;"><?php 
        echo esc_html( $from_the_maker );
        ?></p>
							<p style="margin:0 0 6px 0;font-size:14px;line-height:1.6;color:#3b4452;"><?php 
        echo esc_html( $maker_intro );
        ?></p>
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
								<?php 
        echo wp_kses_post( $product_rows );
        ?>
							</table>
						</div>
					</td>
				</tr>
				<tr>
					<td style="padding:24px 28px 30px 28px;">
						<div style="border-top:1px solid #eceef1;padding-top:18px;">
							<p style="margin:0 0 8px 0;font-size:12px;line-height:1.6;color:#9aa3af;"><?php 
        echo wp_kses_post( $why_receiving );
        ?></p>
							<p style="margin:0;font-size:12px;line-height:1.6;color:#9aa3af;"><?php 
        echo wp_kses_post( $made_by );
        ?></p>
						</div>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
		<?php 
        return (string) ob_get_clean();
    }

    /**
     * Build the plain-text body for the duplicate-deletion status email.
     *
     * Sent as the multipart/alternative AltBody so recipients whose mail client
     * does not render HTML still get a clean, readable message.
     *
     * @param array $data Data from gather_status_email_data().
     * @return string Plain-text email body.
     */
    public static function build_status_email_text( $data ) {
        $blogname = $data['blogname'];
        $deleted_num = number_format_i18n( $data['deleted'] );
        $lines = array();
        // translators: %s: Number of duplicate posts deleted in this run.
        $lines[] = sprintf( __( '%s duplicate posts removed', 'delete-duplicate-posts' ), $deleted_num );
        $lines[] = '';
        $lines[] = sprintf( 
            // translators: %s: Blog name.
            __( "Hi Admin, here's a summary of the latest cleanup on %s.", 'delete-duplicate-posts' ),
            $blogname
         );
        $lines[] = '';
        $lines[] = __( 'View your site:', 'delete-duplicate-posts' ) . ' ' . $data['blogurl'];
        $lines[] = '';
        $lines[] = __( 'RUN SUMMARY', 'delete-duplicate-posts' );
        $lines[] = '----------------------------------------';
        foreach ( $data['details'] as $label => $value ) {
            $lines[] = $label . ': ' . $value;
        }
        $lines[] = '';
        $lines[] = '----------------------------------------';
        $lines[] = '';
        $lines[] = __( 'From the team behind Delete Duplicate Posts', 'delete-duplicate-posts' );
        $lines[] = __( 'We build simple tools that keep WordPress sites clean, fast and secure. You might also like:', 'delete-duplicate-posts' );
        $lines[] = '';
        foreach ( self::get_status_email_products() as $product ) {
            $lines[] = '* ' . $product['name'] . ' — ' . $product['url'];
            $lines[] = '  ' . $product['desc'];
            $lines[] = '';
        }
        $lines[] = '----------------------------------------';
        $lines[] = '';
        $lines[] = sprintf( 
            // translators: %s: Plugin name "Delete Duplicate Posts".
            __( 'You are receiving this email because email notifications are enabled in %s.', 'delete-duplicate-posts' ),
            __( 'Delete Duplicate Posts', 'delete-duplicate-posts' )
         );
        $lines[] = sprintf( 
            // translators: %s: "cleverplugins.com".
            __( 'Made with care by %s', 'delete-duplicate-posts' ),
            'cleverplugins.com (https://cleverplugins.com)'
         );
        return implode( "\n", $lines );
    }

    /**
     * Cross-promotion items shown in the status email (shared by HTML and text).
     *
     * @return array<int,array<string,string>> List of products with name, desc and url.
     */
    private static function get_status_email_products() {
        return array(array(
            'name' => __( 'WP Security Ninja', 'delete-duplicate-posts' ),
            'desc' => __( 'Complete WordPress protection — firewall, malware scanner, scheduled scans and security tests.', 'delete-duplicate-posts' ),
            'url'  => 'https://wpsecurityninja.com/',
        ), array(
            'name' => __( 'SEO Booster', 'delete-duplicate-posts' ),
            'desc' => __( 'Find and fix the keywords your site already ranks for and grow your search traffic.', 'delete-duplicate-posts' ),
            'url'  => 'https://cleverplugins.com/seo-booster/',
        ));
    }

    /**
     * Attach the plain-text alternative body to the outgoing status email.
     *
     * Hooked on phpmailer_init only while the status email is being sent so the
     * message goes out as multipart/alternative (HTML + plain text).
     *
     * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance (by reference).
     * @return void
     */
    public static function add_plain_text_alt_body( $phpmailer ) {
        if ( '' !== self::$status_email_text ) {
            $phpmailer->AltBody = self::$status_email_text;
        }
    }

}

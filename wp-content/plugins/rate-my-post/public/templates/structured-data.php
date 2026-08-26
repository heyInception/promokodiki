<?php

/**
 * Public template
 *
 * @link       http://wordpress.org/plugins/rate-my-post/
 * @since      2.9.2
 *
 * @package    Rate_My_Post
 * @subpackage Rate_My_Post/public/partials
 */

$average_rating = Rate_My_Post_Common::get_average_rating($post_id);
$vote_count     = Rate_My_Post_Common::get_vote_count($post_id);
$schema_type    = $this->schema_type();
$max_rating     = Rate_My_Post_Common::max_rating();
$image          = $this->schema_image($post_id);
$name           = wp_strip_all_tags(get_the_title($post_id));
$description    = $name;

$schema_array = apply_filters('rmp_raw_structured_data', [
    '@context'        => 'http://schema.org',
    '@type'           => $schema_type,
    'aggregateRating' => [
        '@type'       => 'AggregateRating',
        'bestRating'  => strval($max_rating),
        'ratingCount' => strval(absint($vote_count)),
        'ratingValue' => strval($average_rating)
    ],
    'image'           => $image,
    'name'            => $name,
    'description'     => $description
], $post_id, $average_rating, $vote_count, $schema_type, $max_rating, $image, $name);

// Convert to JSON
$json = json_encode($schema_array, JSON_UNESCAPED_SLASHES);
?>

<script type="application/ld+json"><?php echo $json; ?></script>
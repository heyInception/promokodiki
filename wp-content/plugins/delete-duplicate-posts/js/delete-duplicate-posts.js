/* globals jQuery:true, ajaxurl:true, cp_ddp:true  */
jQuery(document).ready(function ($) {

	var startTime;
	var interval;

	// Persist dismissal of plugin admin notices when the user clicks the native WP close button.
	$(document).on('click', '.ddp-dismissible-notice .notice-dismiss', function () {
		var key = $(this).closest('.ddp-dismissible-notice').data('ddp-dismiss');
		if (!key) {
			return;
		}
		$.post(ajaxurl, {
			action: 'ddp_dismiss_notice',
			notice: key,
			_ajax_nonce: cp_ddp.dismiss_notice_nonce
		});
	});

	ddp_refresh_log();

	var redirTable = jQuery('#ddp_redirtable').DataTable({
		"processing": true,
		"serverSide": true,
		"autoWidth": true,
		"ordering": true,
		"ajax": {
			"url": ajaxurl,
			"type": "POST",
			"data": function(d) {
				return jQuery.extend({}, d, {
					"action": "ddp_get_redirects",
					"_ajax_nonce": cp_ddp.nonce
				});
			},
			"dataSrc": function (json) {
				return json.data;
			}
		},
		"columns": [
			{ "data": "ID", "visible": false},
{ "data": "from_url", "title": cp_ddp.fromUrlTitle },
{ "data": "target_url", "title": cp_ddp.targetUrlTitle }
		],
		"select": {
			style: 'multi'
		},
		"dom": '<"top"Blfip>rt<"bottom"Blfip>',
		"buttons": [
			{
				text: cp_ddp.refreshText,
				action: function ( e, dt, node, config ) {
					var $button = $(node);
					$button.prop('disabled', true).text(cp_ddp.refreshingText);
					dt.ajax.reload(function() {
						$button.prop('disabled', false).text(cp_ddp.refreshText);					});
				},
				className: 'button button-secondary button-small'
			}
			// ... other existing buttons ...
		],
		"pageLength": 10,
		"rowCallback": function (row, data) {
			jQuery(row).addClass('wp-list-table widefat fixed striped table-view-list');
		},
		"lengthMenu": [[10, 25, 50, 100, 250, 500], [10, 25, 50, 100, 250, 500]] 
	});

	// Custom error handling for redirTable
	redirTable.on('error.dt', function (e, settings, techNote, message) {
		e.preventDefault(); // Prevent default alert
		console.error('RedirectsDataTables error:', message, 'TechNote:', techNote);

// Use the variables in your code
var errorDetails = cp_ddp.errorDetailsText + message + (techNote ? ' (Tech note: ' + techNote + ')' : '');
jQuery("#ddp-dashboard .errormessage").html(cp_ddp.redirectsErrorText + errorDetails).show();

	});

	redirTable.buttons().container().appendTo('#ddp_redirtable_wrapper .top');

	var table = jQuery('#ddp_dupetable').DataTable({
		"select": {
			style: 'multi'
		},
		"autoWidth": true,
		"processing": true,
		language: {
processing: '<div id="processingMessage">' + cp_ddp.processingMessage + '</div>'		
},
		"serverSide": true,
		"searching": false,
		"ordering": false,
		"dom": '<"top"lip>rt<"bottom"ip><"clear">',
		"ajax": {
			"url": ajaxurl,
			"type": "POST",
			"data": function (d) {
				return jQuery.extend({}, d, {
					"action": "ddp_get_duplicates",
					"_ajax_nonce": cp_ddp.nonce
				});
			},
			"dataSrc": function (json) {
				if (json.error) {
					jQuery("#ddp-dashboard .errormessage").html(json.error).show();
					return [];
				}
				return json.data;
			},
			"beforeSend": function () {
				startTime = new Date().getTime();
				jQuery('#requestTime').html(cp_ddp.requestTimeText);
				interval = setInterval(updateTime, 1000);
				jQuery("#ddp_dupetable .dt-button").prop('disabled', true);
				jQuery('#ddp_dupetable tbody').css('opacity', '0.5');
			},
			"complete": function () {
				clearInterval(interval);
				jQuery('#ddp_dupetable tbody').css('opacity', '1');
				jQuery("#ddp_dupetable .dt-button").prop('disabled', false);
				ddp_refresh_log();
			},
			"error": function (jqXHR, textStatus, errorThrown) {
				console.error('AJAX error:', textStatus, errorThrown);
				var errorDetails = 'Status: ' + textStatus + ', Error: ' + errorThrown + ', Response: ' + jqXHR.responseText;

// Use the variables in your code
jQuery("#ddp-dashboard .errormessage").html(cp_ddp.failedToLoadDataText + errorDetails).show();
				return []; // Return empty data to prevent further errors
			}
		},
		"columns": [
			{ "data": "ID", "visible": false },
			{ "data": "orgID", "visible": false },
{ "data": "duplicate", "title": cp_ddp.duplicateTitle, "orderable": false },
{ "data": "original", "title": cp_ddp.originalTitle, "orderable": false }
		],
		"rowCallback": function (row) {
			jQuery(row).addClass('wp-list-table widefat fixed striped table-view-list');
		},
		"lengthMenu": [[10, 25, 50, 100, 250, 500], [10, 25, 50, 100, 250, 500]] 
	});

	// Custom error handling for table
	table.on('error.dt', function (e, settings, techNote, message) {
		e.preventDefault(); // Prevent default alert
		// console.error('DataTables error:', message, 'TechNote:', techNote);
		var errorDetails = cp_ddp.errorDetailsText + message + (techNote ? ' (Tech note: ' + techNote + ')' : '');
		jQuery("#ddp-dashboard .errormessage").html(cp_ddp.dataTablesErrorText + errorDetails).show();
	});

	// Suppress all DataTables alert dialogs globally
	$.fn.dataTable.ext.errMode = 'none';

	// Create and insert buttons
	var buttonsDiv = createButtons();
	buttonsDiv.insertBefore('#ddp_dupetable_paginate');

	/**
	 * refreshTable.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Friday, January 5th, 2024.
	 * @return	void
	 */
	function refreshTable() {
		table.ajax.reload();
	}

	/**
	 * deleteSelected.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Friday, January 5th, 2024.
	 * @global
	 * @return	void
	 */
	function deleteSelected() {
		var selectedRows = table.rows({ selected: true }).data();

		// Check if there are any selected rows
		if (selectedRows.length === 0) {
			alert(cp_ddp.selectRowAlert);
				return;
		}

		// Existing confirmation and deletion code
		if (!confirm(cp_ddp.text_areyousure)) {
				return;
		}
		
		var checked_posts = [];
		jQuery.each(selectedRows, function(index, value) {
				checked_posts.push({
						'ID': value.ID,
						'orgID': value.orgID
				});
		});

		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
					'_ajax_nonce': cp_ddp.deletedupes_nonce,
					'action': 'ddp_delete_duplicates',
					'checked_posts': checked_posts
			},
			success: function (response) {
				if (response.success) {
					table.ajax.reload(null, false);
					ddp_refresh_log();
				} else {
					var errorMessage = response.data && response.data.message ? response.data.message : cp_ddp.unknownErrorText;
					alert(cp_ddp.serverResponseText + errorMessage);
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
				// Handle other types of errors (e.g., network errors, server errors)
				alert(cp_ddp.errorOccurredText + ' ' + textStatus);
			}
		});
	}

	/**
	 * selectVisible.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Friday, January 5th, 2024.
	 * @global
	 * @return	void
	 */
	function selectVisible() {
		table.rows({ page: 'current' }).select();
	}

	/**
	 * selectNone.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Friday, January 5th, 2024.
	 * @global
	 * @return	void
	 */
	function selectNone() {
		table.rows().deselect();
	}

	/**
	 * createButtons.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Friday, January 5th, 2024.
	 * @global
	 * @return	mixed
	 */
	function createButtons() {
		var buttonsDiv = jQuery('<div/>', { class: 'dt-buttons' });

		buttonsDiv.append(jQuery('<button/>', {
			text: cp_ddp.refreshText,
			click: refreshTable,
			class: 'dt-button button button-secondary button-small'
		}));

		buttonsDiv.append(jQuery('<button/>', {
			text: cp_ddp.deleteSelectedText,
			click: deleteSelected,
			class: 'dt-button button button-secondary button-small ddp-delete-selected'
		}));

		buttonsDiv.append(jQuery('<button/>', {
			text: cp_ddp.selectVisibleText,
			click: selectVisible,
			class: 'dt-button button button-secondary button-small'
		}));

		buttonsDiv.append(jQuery('<button/>', {
			text: cp_ddp.selectNoneText,
			click: selectNone,
			class: 'dt-button button button-secondary button-small'
		}));

		return buttonsDiv;
	}

	/**
	 * updateTime.
	 *
	 * @author	Unknown
	 * @since	v0.0.1
	 * @version	v1.0.0	Tuesday, October 31st, 2023.
	 * @global
	 * @return	void
	 */
	function updateTime() {
		var currentTime = new Date().getTime();
		var elapsedTime = (currentTime - startTime) / 1000;
		jQuery('#requestTime').html(cp_ddp.requestTimeText + elapsedTime + " sec.");
		jQuery('#processingMessage').html(cp_ddp.processingMessage + elapsedTime + " sec.");
		
	}

	/**
	 * Add event listener for row selection
	 *
	 * @var		mixed	#ddp_dupetabl
	 * @global
	 */
	jQuery('#ddp_dupetable tbody').on('click', 'tr', function () {
		jQuery(this).toggleClass('selected');
	});

	/**
	 * ddp_refresh_log.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Sunday, January 10th, 2021.
	 * @return	void
	 */
	function ddp_refresh_log() {
		jQuery('#ddp_log').empty();
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				'_ajax_nonce': cp_ddp.loglines_nonce,
				'action': 'ddp_get_loglines'
			},
			dataType: "json",
			success: function (response) {
				let loglines = response.data.results;
				if (loglines) {
					jQuery.each(loglines, function (key, value) {
						jQuery('#ddp_log').append('<li><code>' + value.datime + '</code> ' + value.note + '</li>');
					});
				}
				jQuery('#log .spinner').removeClass('is-active');
			}
		}).fail(function (response) {
			jQuery('#log .spinner').removeClass('is-active');
			if (window.console && window.console.log) {
				window.console.log(response.statusCode + ' ' + response.statusText);
			}
		});
	}

	// Show / hide input fields in admin based on selected compare method.
	jQuery(document).on('click', '.ddpcomparemethod li', function () {
		jQuery(".ddpcomparemethod input:radio").each(function () {
			if (this.checked) {
				jQuery(this).closest('li').find('.ddp-compare-details').show();
			}
			else {
				jQuery(this).closest('li').find('.ddp-compare-details').hide();
			}
		});
	});

	// Pretend click
	jQuery('.ddpcomparemethod li').trigger('click');


});
jQuery(document).ready(function ($) {

    // =============================
    // Delete All Pages
    // =============================
    $('input[name="abdfw_submit_delete_all_pages"]').on('click', function (e) {
        e.preventDefault();

        const $checkbox = $('#abdfw_delete_all_pages');
        const isChecked = $checkbox.prop('checked');

        if (!isChecked) {
            $checkbox.css('border-color', 'red');
            alert("Please check the 'Delete all pages' option!");
            return;
        }

        $checkbox.css('border-color', ''); // reset error state

        if (confirm("Are you sure you want to delete all pages?")) {
            $.ajax({
                url: abdfw_abdfw_ajax_object.ajaxurl,
                type: 'POST',
                data: {
                    action: 'abdfw_custom_delete_all_pages',
                    custom_delete_all_pages_nonce: $('#custom_delete_all_pages_nonce').val()
                },
                beforeSend: function () {
                    $(".abdfw_loader").show();
                },
                success: function (response) {
                    alert(response === true ? 'Pages deleted successfully.' : 'No pages found to delete.');
                    location.reload();
                },
                error: function (xhr, status, error) {
                    alert('Error deleting pages: ' + error);
                },
                complete: function () {
                    $(".abdfw_loader").hide();
                }
            });
        }
    });

    // =============================
    // Delete Selected Post Types
    // =============================
    $('#abdfw_delete_post_types_button').on('click', function (e) {
        e.preventDefault();

        const $checkboxes = $('input[name="post_types[]"]');
        const selectedTypes = $checkboxes.filter(':checked').map(function () {
            return $(this).val();
        }).get();

        // Validate selection
        if (selectedTypes.length === 0) {
            alert('Please select at least one post type!');
            $checkboxes.css('outline', '2px solid red');
            return;
        }
        $checkboxes.css('outline', ''); // reset highlight

        // Confirm deletion
        if (!confirm("Are you sure you want to delete the selected post types?")) {
            return;
        }

        // AJAX request
        $.ajax({
            url: abdfw_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'abdfw_delete_post_types',
                post_types: selectedTypes,
                security: $('#custom_delete_post_types_nonce').val()
            },
            beforeSend: function () {
                $(".abdfw_loader").show();
            },
            success: function (response) {
                if (response.success) {
                    const messages = Object.values(response.data).join('\n');
                    alert(messages);
                    location.reload();
                } else {
                    alert('Error deleting post types! Please try again.');
                }
            },
            error: function (xhr, status, error) {
                alert('AJAX Error: ' + error);
                console.error(xhr.responseText);
            },
            complete: function () {
                $(".abdfw_loader").hide();
            }
        });
    });

    // =============================
    // Delete All Media Files
    // =============================
    $('#abdfw_delete_media_button').on('click', function (e) {
        e.preventDefault();

        const $checkbox = $('#abdfw_deleteAllMedia');
        const isChecked = $checkbox.prop('checked');

        // Validation
        if (!isChecked) {
            alert("Please check the 'Delete all media' option!");
            $checkbox.css('outline', '2px solid red');
            return;
        }
        $checkbox.css('outline', '');

        // Confirm action
        if (!confirm("Are you sure you want to delete all media files?")) {
            return;
        }

        // AJAX request
        $.ajax({
            url: abdfw_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'abdfw_delete_all_media',
                security: $('#delete_media_nonce').val()
            },
            beforeSend: function () {
                $(".abdfw_loader").show();
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data || 'All media files deleted successfully.');
                } else {
                    alert(response.data || 'No media files found to delete.');
                }
                location.reload();
            },
            error: function (xhr, status, error) {
                alert('AJAX Error: ' + error);
                console.error(xhr.responseText);
            },
            complete: function () {
                $(".abdfw_loader").hide();
            }
        });
    });

    // =============================
    // Delete All Comments
    // =============================
    $('#abdfw_delete_comments_button').on('click', function (e) {
        e.preventDefault();

        const $checkbox = $('#abdfw_deleteAllComments');
        const isChecked = $checkbox.prop('checked');

        // Validation
        if (!isChecked) {
            alert("Please check the 'Delete all comments' option!");
            $checkbox.css('outline', '2px solid red');
            return;
        }
        $checkbox.css('outline', '');

        // Confirm
        if (!confirm("Are you sure you want to delete all comments?")) {
            return;
        }

        // AJAX
        $.ajax({
            url: abdfw_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'abdfw_delete_all_comments',
                security: $('#delete_comments_nonce').val()
            },
            beforeSend: function () {
                $(".abdfw_loader").show();
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data || "All comments deleted successfully.");
                    location.reload();
                } else {
                    alert(response.data || "No comments found to delete.");
                }
            },
            error: function (xhr, status, error) {
                alert("AJAX Error: " + error);
                console.error(xhr.responseText);
            },
            complete: function () {
                $(".abdfw_loader").hide();
            }
        });
    });

    // ==================================
    // Manage From / To Date Restrictions
    // ==================================
    jQuery(function ($) {
        // Get today's date in YYYY-MM-DD
        const today = new Date().toISOString().split('T')[0];

        // Apply max limit = today
        $('#abdfw-from-date, #abdfw-to-date').attr('max', today);

        // When from-date changes, update to-date restrictions
        $('#abdfw-from-date').on('change', function () {
            const fromDate = $(this).val();

            // Set min for to-date
            $('#abdfw-to-date').attr('min', fromDate);

            // If already selected to-date < from-date, reset it
            const toDate = $('#abdfw-to-date').val();
            if (toDate && toDate < fromDate) {
                $('#abdfw-to-date').val('');
            }
        });
    });

    // ==================================
    // Get Image Count From Two Dates
    // ==================================
    jQuery("#abdfw-date-range-form").submit(function(event) {
        event.preventDefault();
        var fromDate = jQuery("#abdfw-from-date").val();
        if(!fromDate){
            jQuery("#abdfw-from-date").css('border-color','red');
            return;
        } else {
            jQuery('#abdfw-from-date').css('border-color', '');
        }
        var toDate = jQuery("#abdfw-to-date").val();
        if(!toDate){
            jQuery("#abdfw-to-date").css('border-color','red');
            return;
        }else {
            jQuery("#abdfw-to-date").css('border-color','');
        }
        jQuery.ajax({
            type: "POST",
            url: abdfw_ajax_object.ajaxurl, 
            data: {
                action: "abdfw_get_image_count_by_date",
                from_date: fromDate,
                to_date: toDate,
                security: jQuery('#date_images_nonce_field').val() // Ensure nonce matches the one in PHP

            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(response) {
                console.log(response);
                var count = parseInt(response);
                if (count > 0) {
                    jQuery("#abdfw-image-count-result").html("<p>Number of images uploaded between " + fromDate + " and " + toDate + ": " + count + "</p><input type='submit' id='abdfw-download-images-between-dates' value='Download' class='button-primary'>" + "<input type='submit' value='Delete Images' id='delete-dates-selected-images' class='button-secondary'>");
                } else {
                    jQuery("#abdfw-image-count-result").html("No images uploaded between " + fromDate + " and " + toDate);
                }
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            }
        });
    });

    // ==================================
    // Get Image Count From Years
    // ==================================
    jQuery('#abdfw-year-form').on('submit', function(e) {
        e.preventDefault(); // Prevent form submission        
        var year = jQuery('select[name="abdfw-year"]').val();
        if(year == '0'){
            jQuery('select[name="abdfw-year"]').css('border-color','red');
            return;
        } else {
            jQuery('select[name="abdfw-year"]').css('border-color','');
        }
        jQuery.ajax({
            url: abdfw_ajax_object.ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'abdfw_get_image_count_by_year',
                year: year,
                security : jQuery('#year_images_nonce_field').val()
            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(response) {
                var count = parseInt(response);
                if(count > 0) {
                    jQuery('#abdfw-image-count').html('<p>Number of images for ' + year + ': ' + response+'</p>');
                    // Add download button after updating the image count
                    var downloadButton = '<input type="button" id="abdfw_download_media_by_years" name="abdfw_download_media_by_years" value="Download" class="button-primary">';
                    var deleteButton = '<input type="submit" value="Delete Images" name="abdfw_delete_images_by_year" id="abdfw_delete_images_by_year" class="button-secondary">';
                
                    jQuery('#abdfw-image-count').append(downloadButton);
                    jQuery('#abdfw-image-count').append(deleteButton);                    
                } else {
                    jQuery('#abdfw-image-count').html('No images found for ' + year);
                }               
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            }
        });
    });


    // ==================================
    // Get Image Count By Authors
    // ==================================
    jQuery('#abdfw-author-form').on('submit', function(e) {
        e.preventDefault(); // Prevent form submission        
        var authorId = jQuery('#author_id').val();
        if(authorId == '0'){
            jQuery('select[name="author_id"]').css('border-color','red');
            return;
        } else {
            jQuery('select[name="author_id"]').css('border-color','');
        }
        jQuery.ajax({
            url: abdfw_ajax_object.ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'abdfw_get_images_by_author',
                author_id: authorId,
                security : jQuery('#author_images_nonce_field').val()
            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(response) {
                var count = parseInt(response);
                if(count > 0){
                    jQuery('#abdfw-author-result').html('<p>Total number of images authored by selected author: '+ response + '</p>' + '<input type="submit" value="Download" name="abdfw_download_media_by_author" id="abdfw_download_media_by_author" class="button-primary">' + '<input type="submit" value="Delete Images" id="abdfw_delete_media_by_author" class="button-secondary">');
                } else {
                    jQuery('#abdfw-author-result').html('<p>No images found!</p>');
                }
                //jQuery('#author-result').html('<p>Total number of images authored by selected author: '+ response + '</p>');                
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            }
        });
    });

    // ==================================
    // Search Month Wise Images
    // ==================================
    $('#abdfw_search_monthswise_image').on('submit', function(e) {
        e.preventDefault(); // Prevent form submission
        var abdfw_month_year = $('select[name="abdfw_month_year"]').val();
        if(abdfw_month_year == '0'){
            jQuery('select[name="abdfw_month_year"]').css('border-color','red');
            return;
        }else {
            jQuery('select[name="abdfw_month_year"]').css('border-color','');
        }
        $.ajax({
            url: abdfw_ajax_object.ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'abdfw_get_images_by_month_year',
                abdfw_month_year: abdfw_month_year,
                security: jQuery('#monthswise_images_nonce_field').val()
            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(response) {
                var count = parseInt(response);
                if(count > 0){               
                    var month_year = jQuery("select[name='abdfw_month_year']").val();
                    $('#abdfw_monthswise_images_display').html('<p>Number of images for ' + month_year + ': ' + response + '</p><input type="submit" value="Download" name="abdfw_download_media_by_month_year" id="abdfw_download_media_by_month_year" class="button-primary">' + '<input type="submit" value="Delete Images" name="abdfw_delete_media_by_month_year" id="abdfw_delete_media_by_month_year" class="button-secondary">');
                } else {
                    var month_year = jQuery("select[name='abdfw_month_year']").val();
                    $('#abdfw_monthswise_images_display').html('No images found for ' + month_year);
                }       
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            }
        });
    });

    // ==================================
    // Delete Media By Author
    // ==================================
    jQuery(document).on('click', '#abdfw_delete_media_by_author', function(e) {
        e.preventDefault();
        var authorID = $('#author_id').val();
        var nonce = $('#delete_media_nonce').val();
        var selectedName = $('#author_id option:selected').text();
        if(authorID == "0"){
            jQuery('select[name="author_id"]').css('border-color','red');
            return;
        }else {
            jQuery('select[name="author_id"]').css('border-color','');
        }
        var confirmDelete = confirm('Are you sure you want to delete all media for this author?');
        if(confirmDelete){
            $.ajax({
                url: abdfw_ajax_object.ajaxurl, // WordPress AJAX
                type: 'POST',
                data: {
                    'action': 'abdfw_delete_media_by_author', // Your action name
                    'author_id': authorID, // Selected author ID
                    'nonce': nonce // Security nonce
                },
                beforeSend: function()
                {
                    $(".abdfw_loader").show();
                },
                success: function(response) {
                    $('#abdfw-author-result').html('All images deleted for '+selectedName+' author.');
                },
                complete:function(response)
                {
                    jQuery(".abdfw_loader").hide();
                }
            });
        }
    });

    // ==================================
    // Delete Media By Month Wise
    // ==================================
    jQuery(document).on('click', '#abdfw_delete_media_by_month_year', function(e) {
        e.preventDefault(); // Prevent the default form submission
        var formData = jQuery('#abdfw_search_monthswise_image').serialize(); // Serialize form data
        var formDataObject = {};
        formData.split('&').forEach(function(keyValue) {
            var pair = keyValue.split('=');
            formDataObject[pair[0]] = decodeURIComponent(pair[1].replace(/\+/g, ' '));
        });
        // Extract the value of the 'month_year' field
        var monthYearValue = formDataObject['abdfw_month_year'];
        if(monthYearValue == '0'){
            jQuery('select[name="abdfw_month_year"]').css('border-color','red');
            return;
        } else{
            jQuery('select[name="abdfw_month_year"]').css('border-color','');
        }
        // Confirm deletion
        var confirmDelete = confirm('Are you sure you want to delete media for the selected month-year?');
        if(confirmDelete){
            // AJAX request to delete media for the selected month-year
            jQuery.ajax({
                url: abdfw_ajax_object.ajaxurl, // WordPress AJAX URL
                type: 'POST',
                data: formData + '&action=abdfw_delete_media_by_month_year', // Append action parameter
                beforeSend: function()
                {
                    jQuery(".abdfw_loader").show();
                },
                success: function(response) {
                    // console.log(response.data);
                    // alert(response.data); // Display success message
                    jQuery('#abdfw_monthswise_images_display').html('All images deleted for ' + monthYearValue);
                },
                complete:function(response)
                {
                    jQuery(".abdfw_loader").hide();
                },
                error: function(error) {
                    console.log('Error:', error);
                }
            });
        }
    });


    // =======================================
    // Delete Media Between Two Selected Dates
    // =======================================
    jQuery(document).on('click', '#delete-dates-selected-images', function(e) {
        e.preventDefault(); // Prevent form submission
        var from_date = jQuery('#abdfw-from-date').val();
        var to_date = jQuery('#abdfw-to-date').val();
        if(!from_date){
            jQuery("#abdfw-from-date").css('border-color','red');
            return;
        } else {
            jQuery("#abdfw-from-date").css('border-color','');
        }
        if(!to_date){
            jQuery("#abdfw-to-date").css('border-color','red');
            return;
        }else {
            jQuery("#abdfw-to-date").css('border-color','');
        }
        if (from_date && to_date) {
            // Confirm deletion
            var confirmDelete = confirm('Are you sure you want to delete images between the selected dates?');
            if (confirmDelete) {
                // Send AJAX request to delete images between selected dates
                jQuery.ajax({
                    url: abdfw_ajax_object.ajaxurl, // WordPress AJAX URL
                    type: 'POST',
                    data: {
                        action: 'abdfw_delete_images_between_dates',
                        from_date: from_date,
                        to_date: to_date,
                        nonce: jQuery('#date_images_nonce_field').val() // Nonce
                    },
                    beforeSend: function()
                    {
                        jQuery(".abdfw_loader").show();
                    },
                    success: function(response) {
                        // alert(response); // Display success message
                        jQuery("#abdfw-image-count-result").html("All images deleted between " + from_date + " and " + to_date);
                    },
                    complete:function(response)
                    {
                        jQuery(".abdfw_loader").hide();
                    },
                    error: function(error) {
                        console.log('Error:', error);
                    }
                });
            }
        } else {
            alert('Please select both from and to dates.');
        }
    });

    // =======================================
    // Delete Media All Unattached Images
    // =======================================
    $('#abdfw-delete-all-unattached-images').click(function(e) {
        e.preventDefault(); // Prevent default form submission        
        // Confirm deletion
        var confirmDelete = confirm('Are you sure you want to delete all unattached images?');
        if (confirmDelete) {
            // Send AJAX request to delete unattached images
            $.ajax({
                url: abdfw_ajax_object.ajaxurl, // WordPress AJAX URL
                type: 'POST',
                data: {
                    action: 'abdfw_delete_all_unattached_images',
                    nonce: $('#delete_all_unattached_images_nonce_field').val() // Nonce
                },
                beforeSend: function()
                {
                    jQuery(".abdfw_loader").show();
                },
                success: function(response) {
                    alert(response); // Display success message
                },
                complete:function(response)
                {
                    jQuery(".abdfw_loader").hide();
                },
                error: function(error) {
                    console.log('Error:', error);
                }
            });
        }
    });

    // =======================================
    // Delete Media All Attached Images
    // =======================================
    $('#abdfw-delete-all-attached-images').click(function(e) {
        e.preventDefault(); // Prevent default form submission        
        // Confirm deletion
        var confirmDelete = confirm('Are you sure you want to delete all attached images?');
        if (confirmDelete) {
            // Send AJAX request to delete attached images
            $.ajax({
                url: abdfw_ajax_object.ajaxurl, // WordPress AJAX URL
                type: 'POST',
                data: {
                    action: 'abdfw_delete_all_attached_images',
                    nonce: $('#delete_all_attached_images_nonce_field').val() // Nonce
                },
                beforeSend: function()
                {
                    jQuery(".abdfw_loader").show();
                },
                success: function(response) {
                    console.log(response)
                    if (response.success) {
                        alert(response.data); // Display success message
                    } else {
                        alert('Error: ' + response.data); // Display error message
                    }
                },
                complete:function(response)
                {
                    jQuery(".abdfw_loader").hide();
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error:', error);
                }
            });
        }
    });


    // =======================================
    // Delete Media By Year
    // =======================================
    jQuery(document).on('click', '#abdfw_delete_images_by_year', function(e) {
        e.preventDefault(); // Prevent default form submission 
        // Get the selected year from the form
        var selectedYear = jQuery('#abdfw-year-form select[name="abdfw-year"]').val();  
        if(selectedYear == '0'){
            jQuery('select[name="abdfw-year"]').css('border-color','red');
            return;
        }else {
            jQuery('select[name="abdfw-year"]').css('border-color','');
        } 
        // Confirm deletion
        var confirmDelete = confirm('Are you sure you want to delete media for the selected year?');
        if (confirmDelete) {           
            // Send AJAX request to delete media for the selected year
            jQuery.ajax({
                url: abdfw_ajax_object.ajaxurl, // WordPress AJAX URL
                type: 'POST',
                data: {
                    action: 'abdfw_delete_media_by_year',
                    year: selectedYear,
                    nonce: jQuery('#year_images_nonce_field').val() // Nonce
                },
                beforeSend: function()
                {
                    jQuery(".abdfw_loader").show();
                },
                success: function(response) {
                    // alert(response); // Display success message
                    jQuery('#abdfw-image-count').html('All images deleted for ' + selectedYear);
                },
                complete:function(response)
                {
                    jQuery(".abdfw_loader").hide();
                },
                error: function(error) {
                    console.log('Error:', error);
                }
            });
        }
    });

    // =======================================
    // Delete Media All Images
    // =======================================
    $('#abdfw-delete-all-images').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete ALL images?')) {
            return;
        }
        var nonce = $('#delete_all_images_nonce_field').val();
        $.ajax({
            url: abdfw_ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'abdfw_delete_all_images',
                nonce: nonce
            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(response) {
                alert(response.data);
                if (response.success) {
                    // Perform actions on success, e.g., reload the page or update UI elements
                    //location.reload();
                }
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            },
            error: function(xhr, status, error) {
                alert('An error occurred: ' + error);
            }
        });
    });

    // =======================================
    // Download All Images
    // =======================================
    $('#abdfw-download-all-images').click(function(e) {
        e.preventDefault();
        $.ajax({
            type: "POST",
            url: abdfw_ajax_object.ajaxurl,
            data: {
                action: "abdfw_download_all_images",
                nonce : jQuery('#delete_all_images_nonce_field').val(),
            },
            xhrFields: {
                responseType: 'blob'
            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(blob) {
                // Create a link element, use it to download the blob, and then remove it
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                // the filename you want
                a.download = 'all_images.zip';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            }
        });
    });

    // =======================================
    // Download All Attached Images
    // =======================================
    $('#abdfw-download-all-attached-images').click(function(e) {
        e.preventDefault();
        $.ajax({
            type: "POST",
            url: abdfw_ajax_object.ajaxurl,
            data: {
                action: "abdfw_download_attached_images",
                security : jQuery('#delete_all_attached_images_nonce_field').val(),
            },
            xhrFields: {
                responseType: 'blob'
            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(blob) {
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'attached_images.zip';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            }
        });
    });


    // =======================================
    // Download All Unattached Images
    // =======================================
    $('#abdfw-download-all-unattached-images').click(function(e) {
        e.preventDefault();
        $.ajax({
            type: "POST",
            url: abdfw_ajax_object.ajaxurl,
            data: {
                action: "abdfw_download_unattached_images",
                security: jQuery('#delete_all_unattached_images_nonce_field').val(),
            },
            xhrFields: {
                responseType: 'blob'
            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(blob) {
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'unattached_images.zip';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            }
        });
    });


    // =======================================
    // Download Images Between Two Dates
    // =======================================
    jQuery(document).on('click', '#abdfw-download-images-between-dates', function(e) {
        e.preventDefault();
        var fromDate = jQuery("#abdfw-from-date").val();
        var toDate = jQuery("#abdfw-to-date").val();
        jQuery.ajax({
            type: "POST",
            url: abdfw_ajax_object.ajaxurl,
            data: {
                action: "abdfw_download_images_between_dates",
                from_date: fromDate,
                to_date: toDate,
                security: jQuery('#date_images_nonce_field').val(),
            },
            xhrFields: {
                responseType: 'blob' // Set the response type to blob
            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(response) {
                var url = window.URL.createObjectURL(response);
                var a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'download-images-between-' + fromDate + '-to-' + toDate + '-dates.zip';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            }
        });
    });

    // =======================================
    // Download Images By Month Year
    // =======================================
    jQuery(document).on('click', '#abdfw_download_media_by_month_year', function(e) {
        e.preventDefault();
        var formData = jQuery('#abdfw_search_monthswise_image').serialize(); // Serialize form data
        var formDataObject = {};
        formData.split('&').forEach(function(keyValue) {
            var pair = keyValue.split('=');
            formDataObject[pair[0]] = decodeURIComponent(pair[1].replace(/\+/g, ' '));
        });
        // Extract the value of the 'month_year' field
        var monthYearValue = formDataObject['abdfw_month_year'];    
        jQuery.ajax({
            type: "POST",
            url: abdfw_ajax_object.ajaxurl,
            data: {
                action: "abdfw_download_images_by_month_year",
                monthYearValue: monthYearValue,
                security: jQuery('#monthswise_images_nonce_field').val(),
            },
            xhrFields: {
                responseType: 'blob' // Set the response type to blob
            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(response) {
                var url = window.URL.createObjectURL(response);
                var a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'download_images_by_'+monthYearValue+'_month_year.zip';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            }
        });
    });

    // =======================================
    // Download Images By Year
    // =======================================
    jQuery(document).on('click', '#abdfw_download_media_by_years', function(e) {
        e.preventDefault();
        var yearValue = jQuery('#abdfw-year').val(); // Get the selected year
        jQuery.ajax({
            type: "POST",
            url: abdfw_ajax_object.ajaxurl,
            data: {
                action: "abdfw_download_media_by_years",
                yearValue: yearValue,
                nonce: jQuery('#year_images_nonce_field').val() // Nonce for security check
            },
            xhrFields: {
                responseType: 'blob' // Set the response type to blob
            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(response) {
                var url = window.URL.createObjectURL(response);
                var a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'download_images_by_'+yearValue+'_year.zip';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            },
            error: function(xhr, status, error) {
                // Handle errors if any
                console.error(xhr.responseText);
            }
        });
    });

    // =======================================
    // Download Images By Author
    // =======================================
    jQuery(document).on('click', '#abdfw_download_media_by_author', function(e) {
        e.preventDefault();
        var authorId = jQuery('#author_id').val();
        var authorName = jQuery('#author_id option:selected').text();
        jQuery.ajax({
            type: "POST",
            url: abdfw_ajax_object.ajaxurl,
            data: {
                action: "abdfw_download_author_images_callback",
                author_id: authorId, // Corrected parameter name
                security:jQuery('#author_images_nonce_field').val(),
            },
            xhrFields: {
                responseType: 'blob' // Set the response type to blob
            },
            beforeSend: function()
            {
                jQuery(".abdfw_loader").show();
            },
            success: function(response) {
                var url = window.URL.createObjectURL(response);
                var a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'download_'+authorName+'_author_images.zip';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
            },
            complete:function(response)
            {
                jQuery(".abdfw_loader").hide();
            },
            error: function(xhr, status, error) {
                // Handle errors if any
                console.error(xhr.responseText);
            }
        });
    });

    // =======================================
    // Advance page delete code
    // =======================================
    jQuery(function($) {

        $('#abdfw-page-schedule-form').on('submit', function(e){
            e.preventDefault();

            var $message = $('#abdfw_page_schedule_message');
            $message.text('');

            $.post(abdfw_ajax_object.ajaxurl, {
                action: 'abdfw_save_page_cleanup_schedule',
                nonce: $('#abdfw_page_schedule_nonce').val(),
                enabled: $('#abdfw_page_schedule_enabled').is(':checked') ? 1 : 0,
                frequency: $('#abdfw_page_schedule_frequency').val(),
                time: $('#abdfw_page_schedule_time').val(),
                status: $('#abdfw_page_schedule_status').val(),
                author: $('#abdfw_page_schedule_author').val(),
                search: $('#abdfw_page_schedule_search').val(),
                from: $('#abdfw_page_schedule_from').val(),
                to: $('#abdfw_page_schedule_to').val(),
                permanent: $('#abdfw_page_schedule_permanent').is(':checked') ? 1 : 0
            }, function (res) {
                var msg = '';
                if (res.success) {
                    msg = res.data.message || 'Schedule saved.';
                    if (res.data.next_run) {
                        msg += ' Next run: ' + res.data.next_run;
                    }
                    $message
                        .removeClass('error')
                        .addClass('success')
                        .text(msg)
                        .fadeIn();
                } else {
                    $message
                        .removeClass('success')
                        .addClass('error')
                        .text('Error saving schedule.')
                        .fadeIn();
                }
                // Hide message after 3 seconds
                setTimeout(function () {
                    $message.fadeOut();
                }, 3000);
            });
        });

        function abdfw_loadPages() {
            $('#abdfw-page-list').html('<p>Loading…</p>');
            $.post(abdfw_ajax_object.ajaxurl, {
                action: 'abdfw_load_pages',
                nonce: abdfw_ajax_object.nonce,
                search: $('#abdfw-page-search').val(),
                status: $('#abdfw-page-status').val(),
                author: $('#abdfw-page-author').val(),
                from: $('#abdfw-page-from').val(),
                to: $('#abdfw-page-to').val()
            }, function(res){
                if(res.success){
                    $('#abdfw-page-list').html(res.data.html);
                } else {
                    $('#abdfw-page-list').html('<p>Error loading pages.</p>');
                }
            });
        }

        $('#abdfw-load-pages').on('click', function(e){
            e.preventDefault();
            abdfw_loadPages();
        });

        // Delegated event for select all
        $(document).on('change','#abdfw-page-select-all', function(){
            $('#abdfw-page-list input[name="pages[]"]').prop('checked', this.checked);
        });

        $('#abdfw-page-form').on('submit', function(e){
            e.preventDefault();
            var pages = [];
            $('#abdfw-page-list input[name="pages[]"]:checked').each(function(){
                pages.push($(this).val());
            });

            $.post(abdfw_ajax_object.ajaxurl, {
                action: 'abdfw_delete_pages',
                nonce: abdfw_ajax_object.nonce,
                pages: pages,
                permanent: $('#abdfw-page-permanent').is(':checked') ? 1 : 0
            }, function(res){
                alert(res.success ? res.data.message : 'Error deleting pages.');
                abdfw_loadPages();
            });
        });
    });

    jQuery(function($){
        // load posts
        $('#abdfw_post_load').on('click', function(e){
            e.preventDefault();
            $.post(abdfw_ajax_object.ajaxurl, {
                action: 'abdfw_post_load_posts',
                _ajax_nonce: abdfw_ajax_object.nonce,
                post_type: $('[name="post_type"]').val(), 
                search: $('[name="search"]').val(),
                status: $('[name="status"]').val(),
                author: $('[name="author"]').val(),
                from: $('[name="from"]').val(),
                to: $('[name="to"]').val()
            }, function(res){
                if(res.success){
                    $('#abdfw_post_results').html(res.data.html);
                } else {
                    $('#abdfw_post_results').html('<p>Error loading posts.</p>');
                }
            });
        });

        // select all
        $(document).on('change','#abdfw_post_select_all', function(){
            $('#abdfw_post_results input[name="posts[]"]').prop('checked', this.checked);
        });

        // delete posts
        $('#abdfw_post_delete').on('click', function(e){
            e.preventDefault();
            var posts = [];
            $('#abdfw_post_results input[name="posts[]"]:checked').each(function(){
                posts.push($(this).val());
            });
            $.post(abdfw_ajax_object.ajaxurl, {
                action: 'abdfw_post_delete_posts',
                _ajax_nonce: abdfw_ajax_object.nonce,
                posts: posts,
                permanent: $('[name="permanent"]').is(':checked') ? 1 : 0
            }, function(res){
                alert(res.data.message);
                $('#abdfw_post_load').click(); // reload list
            });
        });
    });


    jQuery(function($){
        // Load comments
        $('#abdfw-comment-load').on('click', function(e){
            e.preventDefault();
            $.post(abdfw_ajax_object.ajaxurl, {
                action: 'abdfw_load_comments',
                _ajax_nonce: abdfw_ajax_object.nonce,
                post_type: $('[name="post_type"]').val(),
                search: $('[name="search"]').val(),
                status: $('[name="status"]').val(),
                author: $('[name="author"]').val(),
                from: $('[name="from"]').val(),
                to: $('[name="to"]').val()
            }, function(res){
                if(res.success){
                    $('#abdfw-comment-results').html(res.data.html);
                } else {
                    $('#abdfw-comment-results').html('<p>Error loading comments.</p>');
                }
            });
        });

        // Select all
        $(document).on('change','#abdfw-comment-select-all', function(){
            $('#abdfw-comment-results input[name="comments[]"]').prop('checked', this.checked);
        });

        // Delete comments
        $('#abdfw-comment-delete').on('click', function(e){
            e.preventDefault();
            var comments = [];
            $('#abdfw-comment-results input[name="comments[]"]:checked').each(function(){
                comments.push($(this).val());
            });
            $.post(abdfw_ajax_object.ajaxurl, {
                action: 'abdfw_delete_comments',
                _ajax_nonce: abdfw_ajax_object.nonce,
                comments: comments,
                permanent: $('[name="permanent"]').is(':checked') ? 1 : 0
            }, function(res){
                alert(res.data.message);
                $('#abdfw-comment-load').click(); // reload
            });
        });
    });

});

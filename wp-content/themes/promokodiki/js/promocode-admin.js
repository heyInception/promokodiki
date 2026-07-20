jQuery(document).ready(function($) {
    // Загрузка изображения для категории
    if ($('.ct_tax_media_button').length > 0) {
        if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
            $('.ct_tax_media_button').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var send_attachment_bkp = wp.media.editor.send.attachment;
                wp.media.editor.send.attachment = function(props, attachment) {
                    $('#category-image-id').val(attachment.id);
                    $('#category-image-wrapper').html('<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />');
                    $('#category-image-wrapper .custom_media_image').attr('src', attachment.url).css('display','block');
                    button.siblings('.ct_tax_media_remove').show();
                };
                wp.media.editor.open(button);
                return false;
            });
        }
    }

    // Удаление изображения категории
    $('.ct_tax_media_remove').on('click', function() {
        $('#category-image-id').val('');
        $('#category-image-wrapper').html('');
        $(this).hide();
    });
});
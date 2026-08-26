jQuery(document).ready(function($) {
    // Загрузка изображения
    $('.shops_tax_media_button, .brand_tax_media_button').click(function(e) {
        e.preventDefault();
        var button = $(this);
        var send_attachment_bkp = wp.media.editor.send.attachment;
        
        wp.media.editor.send.attachment = function(props, attachment) {
            $('#shops-category-image-id, #promocode_brand-image-id').val(attachment.id);
            $('#shops-category-image-wrapper, #promocode_brand-image-wrapper').html('<img src="' + attachment.url + '" style="max-width:100%;"/>');
            wp.media.editor.send.attachment = send_attachment_bkp;
        }
        
        wp.media.editor.open(button);
        return false;
    });
    
    // Удаление изображения
    $('.shops_tax_media_remove, .brand_tax_media_remove').click(function() {
        $('#shops-category-image-id, #promocode_brand-image-id').val('');
        $('#shops-category-image-wrapper, #promocode_brand-image-wrapper').html('');
    });
});
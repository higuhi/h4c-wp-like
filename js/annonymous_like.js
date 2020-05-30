jQuery(document).ready(function($) {
    jQuery('.pp_like').click( function(e) {
        e.preventDefault();
        jQuery('.pp_like').hide();
        var postid = jQuery(this).data('id');
        var data = {
            action: 'h4c_annnonymous_like',
            security: h4c_wp_plugin_annonymous_like_ajax.security,
            postid: postid
        };

        jQuery.post(h4c_wp_plugin_annonymous_like_ajax.ajaxurl, data, function(res) {
            var result=jQuery.parseJSON( res );
            console.log(result);

            var likes = result.likecount + " like" + (result.likecount>1 ? 's' : '');
            jQuery('.post_like span').text(likes);
            
            if(result.liked){
                jQuery('.pp_like_like').hide();
                jQuery('.pp_like_liked').show();
            } else {
                jQuery('.pp_like_like').show();
                jQuery('.pp_like_liked').hide();
            }
            
            jQuery('.pp_like').show();
        });
    });
});
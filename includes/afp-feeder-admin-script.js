var $j=jQuery.noConflict();

function toggleafpfbox() {
    $j(this).parents('div:first').toggleClass('closed');

}

function addcategory() {
    var tagname = $j( '#newcategory' ).val();
    var parent = $j( '#newcategory_parent' ).val();
    var checked = $j('input[name="post_category[]"]:checked').serialize();
    var add_cat_nonce = $j( '#add-cat-ajax-nonce' ).val();
    $j.ajax({
        type: 'POST',
        url: ajaxurl,
        data: {
            action: 'add_category',
            taxonomy: 'category',
            "tag-name": tagname,
            parent: parent,
            checked: checked,
            _ajax_nonce: add_cat_nonce,
        },
        success: function (response) {
            $j('#post-categories' ).html( response );
        }
    });
    return false;
}

function toggleCategoryAdder() {
    $j(this).parents('div:first').toggleClass('wp-hidden-children');
    $j('#newcategory').focus();
    return false;
}

$j(document).ready(function () {
        
    $j('#afpf_scheduled').change(toggleafpfbox);
    if (! $j('#afpf_scheduled').is(':checked') ) {
        $j('#afpf_scheduled').trigger('change');
    }
    $j('#post-categories').delegate('.handlediv', 'click', toggleafpfbox);   
    
    $j('#post-categories').delegate('#category-add-submit', 'click', addcategory);
    $j('#post-categories').delegate('#category-add-toggle', 'click', toggleCategoryAdder);
    
});
			
			
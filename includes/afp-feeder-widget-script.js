$j = jQuery.noConflict();

function pagedown() {
    
    var more = $j(this);
    var content = more.siblings('#afpfw-content');
    var afpfw_total_pages = afpfw_vars.afpfw_total_pages;
    var afpfw_max_per_page = afpfw_vars.afpfw_max_per_page;
    var afpfw_paged = parseInt( more.attr('value') ) + 1;
        
    $j.ajax({
        type: 'GET',
        url: afpfw_vars.ajaxurl,
        data: {
            action: 'afpfw_get_page',
            afpfw_paged: afpfw_paged,
            afpfw_max_per_page: afpfw_max_per_page,
            afpfw_ajax: true,
        },
        success: function(html) {
            content.children('.afpfw_page').css("padding-bottom", 0);
            content.append(html);
            content.children('.afpfw_page:last').css("padding-bottom", more.height());
            var height = content.children('.afpfw_page').height() * (afpfw_paged);
            more.attr('value', afpfw_paged);
            if (afpfw_paged >= afpfw_total_pages) {
                more.hide();
            }
            content.animate ({scrollTop:height}, 1000);
        },
    });
    event.preventDefault();
}

$j(document).ready(function () {
    
    $j('.afpfw-container').delegate('#afpfw-more', 'click', pagedown);
    
});
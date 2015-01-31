$j = jQuery.noConflict();
function pagedown() {
    var more = $j(this);
    var content = more.siblings('ul');
    var afpfw_total_pages = afpfw_vars.afpfw_total_pages;
    var afpfw_max_per_page = afpfw_vars.afpfw_max_per_page;
    var afpfw_paged = more.attr('value') ? parseInt(more.attr('value')) + 1 : 2;
    
    if (afpfw_paged > afpfw_total_pages) return false;
    
    $j.ajax({
        type: 'GET', 
        url: afpfw_vars.ajaxurl, 
        data: {action: 'afpfw_get_page', 
            afpfw_paged: afpfw_paged, 
            afpfw_max_per_page: afpfw_max_per_page, 
            afpfw_ajax: true, 
        }, 
        success: function (html) {
            var height = content.parent().closest('div').height() * (afpfw_paged - 1);
            content.append(html);
            more.attr('value', afpfw_paged);
            content.animate({scrollTop: height}, 1000);
        }, 
    });
    event.preventDefault();
}
$j(document).ready(function () {
    $j('.box-afp__header').click(pagedown);
});
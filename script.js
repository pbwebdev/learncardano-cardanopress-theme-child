jQuery(document).ready(function () {
	jQuery(document).on('click', '#navbarSupportedContent li.dropdown a', function () {                
        jQuery("#navbarSupportedContent ul.dropdown-menu").css("display", "none");
        jQuery(this).parent().find("ul.dropdown-menu").toggle();
    });
});

jQuery(document).click(function(e) {    
    if (jQuery(e.target).closest("#navbarSupportedContent").length === 0) {
        jQuery("#navbarSupportedContent ul.dropdown-menu").css("display", "none");
    }    
});

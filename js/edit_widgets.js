/*
// borkweb's crazy talk
var $root = jQuery(document);
$root.delegate('div.postloop.container label', 'click', function(){
	jQuery(this).parent().children('div').slideToggle('fast');
});


jQuery('.postloop .open-if-value').parent().parent().slideDown('fast');

jQuery('.postloop .open-if-value').each( function() {
	var $myThis = jQuery(this);
    if( $myThis.val().length || $myThis.is(':checked') )
		$myThis.parent().parent().slideDown('fast');
});

jQuery('#widget-postloop-4-categories_in-1').parents('div.contents').slideDown('fast');

jQuery('.postloop .open-if-value').parents('div.contents').slideDown('fast');


*/

function postloops_widgeteditor_update( parent_id ) {
//alert( 'Yo!'+ parent_id );

	var parent_id = jQuery( '#' + parent_id ).parent().parent().parent().parent().parent().attr( 'id' );

	if( parent_id )
		postloops_widgeteditor( parent_id );
}

function postloops_widgeteditor( parent_id ) {

	if( parent_id )
		parent_id = '#' + parent_id +' ';
	else
		parent_id = '';

//alert( parent_id );

	// open up the sections appropriate to the query type on load
	jQuery( parent_id + 'select.postloop.querytype_selector').each(function() {
		var $myThis = jQuery(this);
	    var querytype = $myThis.val();
		$myThis.parents('div.widget-content').addClass( 'querytype_' + querytype );
		$myThis.parents('div.widget-content').children('div .container').slideUp('fast');
		$myThis.parents('div.widget-content').children('div .querytype_normal' ).slideDown('fast');
		$myThis.parents('div.widget-content').children('div .querytype_' + querytype ).slideDown('fast');
	});

/*
	// open up the sections appropriate to the post type on load
	jQuery( parent_id + '.querytype_custom select.postloop.posttype_selector').each(function() {
		var $myThis = jQuery(this);
	    var posttype = $myThis.val();
		$myThis.parents('div.widget-content').children('div .container').slideUp('fast');
		$myThis.parents('div.widget-content').children('div .posttype_normal' ).slideDown('fast');
		$myThis.parents('div.widget-content').children('div .posttype_' + posttype ).slideDown('fast');
	});
*/

	// open the sections with set values
	jQuery( parent_id + '.postloop .open-on-value').parents('div.contents').slideDown('fast');

	// make the section headers clickable
	jQuery( parent_id + 'div.postloop.container > label').prepend('<span class="clicker">&darr; </span>');
	jQuery( parent_id + 'div.postloop.container > label').click(function(){
		jQuery(this).parent().children('div').slideToggle('fast');
	});
	
	// make the sections open based on query type
	jQuery(parent_id + 'select.postloop.querytype_selector').change(function() {
		var $myThis = jQuery(this);
	    var querytype = $myThis.val();
		$myThis.parents('div.widget-content').children('div .container').slideUp('fast');
		$myThis.parents('div.widget-content').children('div .querytype_normal' ).slideDown('fast');
		$myThis.parents('div.widget-content').children('div .querytype_' + querytype ).slideDown('fast');
	});

/*
	// make the sections open based on post type
	jQuery(parent_id + 'select.postloop.posttype_selector').change(function() {
		var $myThis = jQuery(this);
	    var posttype = $myThis.val();
		$myThis.parents('div.widget-content').children('div .container').slideUp('fast');
		$myThis.parents('div.widget-content').children('div .posttype_normal' ).slideDown('fast');
		$myThis.parents('div.widget-content').children('div .posttype_' + posttype ).slideDown('fast');
	});
*/

}
/*
Version: .6
*/

function backdataassup_selectAll(){
	// add javascript to `Select All` and `Select None` on left column
	$selectAll = jQuery( 'ul#backdataassup_tables_select li:nth-child(1)' );
	$selectAll.click(function(e){
		jQuery('ol#backdataassup_tables_list li input[type=checkbox]').attr( 'checked', true );
		e.preventDefault();
	});
	
	$selectNone = jQuery( 'ul#backdataassup_tables_select li:nth-child(2)' );
	$selectNone.click(function(e){
		jQuery('ol#backdataassup_tables_list li input[type=checkbox]').attr( 'checked', false );
		e.preventDefault();
	});
	
	// add javascript to `Bulk` table header
	$selectBulk = jQuery( '<a href="#">Bulk</a>' );
	$selectBulk.click(function(e){
		jQuery('table#backdataassup_backups input[type=checkbox]').attr('checked',true);
		e.preventDefault();
	});
	jQuery('table#backdataassup_backups thead th:nth-child(5)').html( $selectBulk );
}

jQuery(document).ready(function(){
	backdataassup_selectAll();
});
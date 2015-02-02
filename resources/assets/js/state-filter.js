$(function() {
	$('select[data-state-filter-country-id]').filteredSelect({
		monitor: function() {
			return $($(this).attr('data-state-filter-country-id'));
		},
		onChange: function() {
			if ($(this).children('option[value!=""]').length == 0) {
				$(this).parent().hide();
			} else {
				$(this).parent().show();
			}
		}
	});
});
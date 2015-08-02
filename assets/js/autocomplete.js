jQuery(document).ready(function( $ ) {
	$('#keyword').autocomplete({
		source: function(req, response){
        $.getJSON(whpt_options.url+'?action=whpt_action', req, response);
    },
		minLength:parseInt(whpt_options.min_length),
		select: function(event,ui){
			var id = ui.item.id;
			if(id != '') {
				$(this).parent().attr('action', id);
			}
		},
         //optional
		html: 'test', 
		//optional (if other layers overlap the autocomplete list)
		open: function(event, ui) {
			$(".ui-autocomplete").css("z-index", 1000);
		}
	});
});
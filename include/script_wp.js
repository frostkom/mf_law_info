jQuery(function($)
{
	$(window).load(function()
	{
		var dom_hash = location.hash.replace('#', '');

		if(dom_hash != '')
		{
			var dom_obj = $("#" + dom_hash);

			if(dom_obj.length > 0)
			{
				$("html, body").animate(
				{
					scrollTop: (dom_obj.offset().top - 40)
				}, 1000);
			}
		}
	});
});
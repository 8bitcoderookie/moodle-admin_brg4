/* *****************************************************
klassenCohortenDel.js

     author: Michael A. Rundel
       date: 22.04.2011
description: Javascript enhancement for klassenCohortenDel.php

****************************************************** */

jQuery(document).ready(function() {
	jQuery("a[href^='klassenCohortenDetails.php']")
		.click(function(e) {
					e.preventDefault();
					var url = jQuery(this).attr("href")+"&ajax=true";
					jQuery(this)
						.parent() // span
						.text("- This cohort is enrolled in the following course(s):")
						.parent() // li
						.after("<ul></ul>")
						.next() // ul
						.load(url);
				})
	jQuery(".folder")
		.prepend("<img src='images/iconFolderOpen16.gif'><input type='checkbox' class='toggleCheck'>")
		.find(".toggleCheck")
		.click(function(e) {
					var v = jQuery(this).attr("checked");
					jQuery(this)
						.parent()
						.next()
						.find(":checkbox:not(:disabled)")
						.attr("checked",v);
					e.stopPropagation()
				})
		.end()
		.toggle(function() {
			jQuery(this)
				.find("img")
				.attr("src",'images/iconFolderClosed16.gif')
				.end()
				.next()
				.slideUp();
		}, function() {
			jQuery(this)
				.find("img")
				.attr("src",'images/iconFolderOpen16.gif')
				.end()
				.next()
				.slideDown();
		})
		.trigger("click");
});


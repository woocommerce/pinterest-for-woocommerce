/*global pintrk*/

/**
 * Bind to search form submit in order to track the search event.
 */
window.onload = function () {
	document.querySelectorAll("form[role='search']").forEach(function (form) {
		form.addEventListener('submit', function () {
			if (typeof pintrk !== 'function') {
				return;
			}

			const searchBox = form.querySelector("input[type='search']");

			if (searchBox) {
				pintrk('track', 'search', { search_query: searchBox.value });
			}
		});
	});
};

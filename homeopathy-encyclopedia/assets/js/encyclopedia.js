(function () {
	'use strict';

	function request(data, action) {
		data.append('action', action || 'he_interaction');
		data.append('nonce', heEncyclopedia.nonce);
		return fetch(heEncyclopedia.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		}).then(function (response) {
			return response.json().then(function (json) {
				if (!response.ok || !json.success) {
					throw new Error(json.data && json.data.message ? json.data.message : heEncyclopedia.messages.genericError);
				}
				return json.data;
			});
		});
	}

	function entryId(element) {
		var owner = element.closest('[data-entry-id]');
		return owner ? owner.getAttribute('data-entry-id') : '';
	}

	function redirectForLogin(error) {
		if (/log in/i.test(error.message)) {
			window.location.href = heEncyclopedia.loginUrl;
			return true;
		}
		return false;
	}

	function loadBookmarkStates() {
		var ids = [];
		document.querySelectorAll('[data-entry-id]').forEach(function (entry) {
			var id = entry.getAttribute('data-entry-id');
			if (id && ids.indexOf(id) === -1) {
				ids.push(id);
			}
		});
		if (!ids.length) {
			return;
		}
		var data = new FormData();
		ids.forEach(function (id) { data.append('entryIds[]', id); });
		request(data, 'he_bookmark_states').then(function (result) {
			if (!result.loggedIn) {
				return;
			}
			document.querySelectorAll('[data-he-action="bookmark"]').forEach(function (button) {
				var id = entryId(button);
				var active = !!result.states[id];
				button.textContent = active ? heEncyclopedia.messages.bookmarked : heEncyclopedia.messages.bookmark;
				button.classList.toggle('is-active', active);
				button.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
		}).catch(function () {
			// Public content remains usable when private-state hydration is unavailable.
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		loadBookmarkStates();
		document.querySelectorAll('.he-form input[type="file"]').forEach(function (input) {
			input.addEventListener('change', function () {
				var file = this.files && this.files[0] ? this.files[0] : null;
				if (!file) {
					return;
				}
				if (file.size > 5 * 1024 * 1024) {
					window.alert(heEncyclopedia.messages.imageTooLarge);
					this.value = '';
					return;
				}
				var inputElement = this;
				var image = new Image();
				image.onload = function () {
					URL.revokeObjectURL(image.src);
					if (image.width > 6000 || image.height > 6000 || image.width * image.height > 24000000) {
						window.alert(heEncyclopedia.messages.imageDimensions);
						inputElement.value = '';
					}
				};
				image.onerror = function () {
					URL.revokeObjectURL(image.src);
					window.alert(heEncyclopedia.messages.imageInvalid);
					inputElement.value = '';
				};
				image.src = URL.createObjectURL(file);
			});
		});
	});

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-he-action]');
		if (!button) {
			return;
		}
		event.preventDefault();
		var desired = button.getAttribute('aria-pressed') !== 'true';
		var data = new FormData();
		data.append('kind', button.getAttribute('data-he-action'));
		data.append('entryId', entryId(button));
		data.append('desired', desired ? '1' : '0');
		button.disabled = true;
		request(data).then(function (result) {
			button.textContent = result.label;
			button.classList.toggle('is-active', !!result.active);
			button.setAttribute('aria-pressed', result.active ? 'true' : 'false');
		}).catch(function (error) {
			if (!redirectForLogin(error)) {
				window.alert(error.message);
			}
		}).finally(function () {
			button.disabled = false;
		});
	});

	document.addEventListener('submit', function (event) {
		var form = event.target.closest('[data-he-feedback]');
		if (!form) {
			return;
		}
		event.preventDefault();
		var data = new FormData(form);
		data.append('kind', form.getAttribute('data-kind'));
		data.append('entryId', entryId(form));
		var button = form.querySelector('button');
		button.disabled = true;
		request(data).then(function (result) {
			var status = document.createElement('p');
			status.setAttribute('role', 'status');
			status.textContent = result.message;
			form.replaceChildren(status);
		}).catch(function (error) {
			if (!redirectForLogin(error)) {
				window.alert(error.message);
			}
		}).finally(function () {
			if (button.isConnected) {
				button.disabled = false;
			}
		});
	});
}());

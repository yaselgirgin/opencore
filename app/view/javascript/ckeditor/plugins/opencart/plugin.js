CKEDITOR.plugins.add('opencart', {
	init: function(editor) {
		editor.addCommand('OpenCart', {
			exec: function(editor) {
				$('#modal-image').remove();

				$.ajax({
					url: 'index.php?route=common/filemanager&user_token=' + getURLVar('user_token') + '&ckeditor=' + editor.name,
					dataType: 'html',
					success: function(html) {
						$('body').append(html);

						tabler.Modal.getOrCreateInstance(document.getElementById('modal-image')).show();
					}
				});
			}
		});

		editor.ui.addButton('OpenCart', {
			label: 'OpenCart',
			command: 'OpenCart',
			icon: this.path + 'images/icon.png'
		});
	}
});

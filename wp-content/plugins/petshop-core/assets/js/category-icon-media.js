(function ($) {
  'use strict';

  function labels() {
    return window.petshopCategoryIconMedia || {};
  }

  function frameFor(root) {
    if (root.data('petshopMediaFrame')) {
      return root.data('petshopMediaFrame');
    }

    const localized = labels();
    const frame = wp.media({
      title: localized.title || 'Selecionar ícone',
      button: {
        text: localized.button || 'Usar este ícone',
      },
      library: {
        type: 'image',
      },
      multiple: false,
    });

    frame.on('select', function () {
      const attachment = frame.state().get('selection').first().toJSON();
      const input = root.find('[data-petshop-icon-attachment-input]');
      const preview = root.find('[data-petshop-icon-attachment-preview]');
      const selectButton = root.find('[data-petshop-icon-attachment-select]');
      const removeButton = root.find('[data-petshop-icon-attachment-remove]');
      const url = attachment.url || (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) || '';

      input.val(String(attachment.id || ''));
      preview.empty();
      if (url) {
        preview.append(
          $('<img>', {
            src: url,
            alt: '',
            width: 64,
            height: 64,
          })
        );
        preview.prop('hidden', false);
      } else {
        preview.prop('hidden', true);
      }
      selectButton.text(localized.labelChange || 'Trocar ícone personalizado');
      removeButton.prop('disabled', false);
    });

    root.data('petshopMediaFrame', frame);
    return frame;
  }

  $(document).on('click', '[data-petshop-icon-attachment-select]', function (event) {
    event.preventDefault();
    const root = $(this).closest('[data-petshop-category-custom-icon]');
    if (!root.length || typeof wp === 'undefined' || !wp.media) {
      return;
    }
    frameFor(root).open();
  });

  $(document).on('click', '[data-petshop-icon-attachment-remove]', function (event) {
    event.preventDefault();
    const root = $(this).closest('[data-petshop-category-custom-icon]');
    const localized = labels();
    root.find('[data-petshop-icon-attachment-input]').val('');
    root.find('[data-petshop-icon-attachment-preview]').empty().prop('hidden', true);
    root.find('[data-petshop-icon-attachment-select]').text(localized.labelSelect || 'Selecionar ícone personalizado');
    $(this).prop('disabled', true);
  });
})(jQuery);

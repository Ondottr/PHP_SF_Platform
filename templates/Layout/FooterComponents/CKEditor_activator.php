<?php declare(strict_types=1);

namespace PHP_SF\Templates\Layout\FooterComponents;

use App\Kernel;
use PHP_SF\System\Classes\Abstracts\AbstractView;

// @formatter:off
final class CKEditor_activator extends AbstractView { public function show(): void { ?>
  <!--@formatter:on-->

  <script src="<?= asset('CKEditor/build/ckeditor.js') ?>"></script>
  <script src="<?= asset('CKEditor/build/marked.js') ?>"></script>
  <script src="<?= asset('CKEditor/build/purify.js') ?>"></script>
  <script>
    const watchdog = new CKSource.EditorWatchdog();

    window.watchdog = watchdog;

    watchdog.setCreator((element, config) => {
      return CKSource.Editor
        .create(element, config)
        .then(editor => {
          window.editor = editor;
          return editor;
        });
    });

    watchdog.setDestructor(editor => {
      return editor.destroy();
    });

    watchdog.on("error", handleError);

    watchdog
      .create(document.querySelector(".editor"), {
        licenseKey: ""
      })
      .catch(handleError);

    function handleError(error) {
      console.error("Oops, something went wrong with CKEditor!");
      console.error(error);
    }

    $('form#editor').on('submit', (event) => {
      event.preventDefault();

      $('#editor_data')[0].value = DOMPurify.sanitize(marked.parse(editor.getData()));

      event.currentTarget.submit();
    })

  </script>


  <?php Kernel::setEditorStatus(false) ?>

  <!--@formatter:off-->
<?php } }
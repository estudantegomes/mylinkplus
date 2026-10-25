document.addEventListener("DOMContentLoaded", function () {
  const imagePreviewInput = document.getElementById("image_preview_input");
  const preview = document.getElementById("image_preview");
  const imagePreviewSubmit = document.getElementById("image_preview_submit");

  if (!(imagePreviewInput && preview)) return;

  imagePreviewInput.style.display = "none";
  imagePreviewSubmit.style.display = "none";

  preview.addEventListener("click", function () {
    imagePreviewInput.click();
  });

  imagePreviewInput.addEventListener("change", function (event) {
    const file = event.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        document.getElementById("image_preview").src = e.target.result;
        imagePreviewSubmit.style.display = "block";
      };
      reader.readAsDataURL(file);
    }
  });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach((alert) => {
        const delay = Number(alert.dataset.autoDismiss);

        const timer = window.setTimeout(() => {
            if (alert.isConnected) {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            }
        }, delay);

        //aviso fechado pelo X.
        alert.addEventListener('close.bs.alert', () => {
            window.clearTimeout(timer);
        }, { once: true });
    });
});

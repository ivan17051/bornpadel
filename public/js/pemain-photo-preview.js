(function () {
    const initPhotoPreview = (input) => {
        const previewId = input.dataset.previewTarget;
        const placeholder = input.dataset.placeholder;
        const preview = previewId ? document.getElementById(previewId) : null;

        if (!preview) {
            return;
        }

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];

            if (!file) {
                preview.src = placeholder;
                return;
            }

            if (!file.type.startsWith('image/')) {
                preview.src = placeholder;
                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                preview.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    };

    document.querySelectorAll('[data-pemain-photo-input]').forEach(initPhotoPreview);
})();

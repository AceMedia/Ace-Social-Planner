(function () {
    const root = document.querySelector('.ace-social-planner');

    if (!root || !window.aceSocialPlanner) {
        return;
    }

    const contentField = document.getElementById('ace-social-planner-content');
    const button = document.getElementById('ace-social-planner-generate');
    const output = document.getElementById('ace-social-planner-output');
    const status = document.getElementById('ace-social-planner-status');

    const setStatus = function (message, isError) {
        status.textContent = message;
        status.className = isError ? 'is-error' : 'is-success';
    };

    button.addEventListener('click', function () {
        const content = contentField.value.trim();

        if (!window.aceSocialPlanner.hasApiKey) {
            setStatus('Save an OpenAI API key first.', true);
            return;
        }

        if (!content) {
            setStatus('Add source content before generating.', true);
            return;
        }

        button.disabled = true;
        setStatus('Generating...', false);
        output.textContent = 'Working...';

        fetch(window.aceSocialPlanner.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.aceSocialPlanner.nonce,
            },
            body: JSON.stringify({ content: content }),
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return {
                        ok: response.ok,
                        data: data,
                    };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    const message = result.data && result.data.message ? result.data.message : 'Generation failed.';
                    throw new Error(message);
                }

                output.textContent = result.data.output_text || 'No output returned.';
                setStatus('Draft generated.', false);
            })
            .catch(function (error) {
                output.textContent = 'No output returned.';
                setStatus(error.message, true);
            })
            .finally(function () {
                button.disabled = false;
            });
    });
})();

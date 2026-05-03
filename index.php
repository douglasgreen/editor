<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Markdown Editor (CSV Version)</title>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .markdown { border: 1px solid #ddd; padding: 20px; background: #f9f9f9; margin-top: 20px; min-height: 100px; }
        textarea { width: 100%; height: 400px; font-family: monospace; box-sizing: border-box; }
        button { margin: 10px 0; padding: 10px; cursor: pointer; }
        .status { font-style: italic; color: #555; }
    </style>
</head>
<body>
    <h1>Edit Document</h1>
    
    <textarea id="content" placeholder="Loading..."></textarea><br>
    <button onclick="saveContent()">Save</button>
    <span id="status" class="status"></span>
    
    <h2>Preview</h2>
    <div id="preview" class="markdown"></div>

    <script>
        const API_URL = 'api.php';
        const contentArea = document.getElementById('content');
        const previewArea = document.getElementById('preview');
        const statusArea = document.getElementById('status');
        
        // Load the latest content from the server on page load
        window.onload = function() {
            fetch(API_URL)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    contentArea.value = data;
                    updatePreview(data);
                })
                .catch(error => {
                    console.error('Error loading content:', error);
                    contentArea.value = 'Error: Could not load content from server.';
                });
        };
        
        // Save content to the CSV by sending it to the server
        function saveContent() {
            const content = contentArea.value;
            statusArea.textContent = 'Saving...';

            fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'text/plain',
                },
                body: content
            })
            .then(response => response.text())
            .then(message => {
                console.log('Server response:', message);
                // Check if the response contains "Error"
                if (message.startsWith("Error")) {
                    statusArea.textContent = message;
                    statusArea.style.color = 'red';
                } else {
                    statusArea.textContent = 'Saved successfully!';
                    statusArea.style.color = '#555';
                    // Clear the status message after a few seconds
                    setTimeout(() => { statusArea.textContent = ''; }, 3000);
                }
            })
            .catch(error => {
                console.error('Error saving content:', error);
                statusArea.textContent = 'Error: Could not save.';
                statusArea.style.color = 'red';
            });
        }
        
        // Update preview with Markdown rendering
        function updatePreview(content) {
            previewArea.innerHTML = marked.parse(content);
        }
        
        // Auto-update preview on textarea input
        contentArea.addEventListener('input', function() {
            updatePreview(this.value);
        });
    </script>
</body>
</html>

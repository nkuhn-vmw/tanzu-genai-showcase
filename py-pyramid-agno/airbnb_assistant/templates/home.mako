<!DOCTYPE html>
<html lang="en" data-theme="${request.session.get('theme', 'light')}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Airbnb Assistant</title>
    <link rel="stylesheet" href="${request.static_url('airbnb_assistant:static/css/style.css')}">
    <!-- DOMPurify for sanitizing HTML -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/2.4.0/purify.min.js"></script>
    <!-- Marked for Markdown support -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.0.2/marked.min.js"></script>
</head>
<body>
    <div class="container">
        <header>
            <h1>Airbnb Assistant</h1>
            <p>Your AI-powered search assistant</p>
            <button id="theme-toggle" class="theme-toggle">
                % if request.session.get('theme', 'light') == 'light':
                    🌙 Dark Mode
                % else:
                    ☀️ Light Mode
                % endif
            </button>
        </header>

        <main>
            <div class="chat-container">
                <div id="chat-messages" class="chat-messages">
                    <div class="message assistant">
                        <div class="message-content">
                            <p>👋 Hello! I'm your Airbnb assistant. How can I help you today?</p>
                            <p>I can help you with:</p>
                            <ul>
                                <li>Finding accommodations in a specific location</li>
                                <li>Getting details about listings</li>
                            </ul>
                            <p>Try asking me something like: <em>"Find me a place to stay in San Francisco"</em></p>
                        </div>
                    </div>
                </div>

                <div class="chat-input">
                    <input type="text" id="user-input" placeholder="Type your message here...">
                    <button id="send-button">Send</button>
                </div>
            </div>
        </main>

        <footer>
            <p>Powered by Pyramid and Agno AI | <a href="https://github.com/cf-toolsuite/tanzu-genai-showcase">GitHub</a></p>
        </footer>
    </div>

    <script src="${request.static_url('airbnb_assistant:static/js/app.js')}"></script>
</body>
</html>
/**
 * Message formatting utilities for the Movie Chatbot
 */

// Format message text, converting markdown-like syntax to HTML
window.formatMessageText = function(text) {
    if (!text) return '';

    // Convert **text** to <strong>text</strong> for bold formatting
    return text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
};

// Apply formatting to all messages in a container
window.formatMessagesInContainer = function(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Find all bot messages in the container
    const botMessages = container.querySelectorAll('.bot-message');

    // Apply formatting to each message
    botMessages.forEach(message => {
        // Get current text content
        const originalText = message.textContent || '';

        // Apply formatting
        const formattedText = formatMessageText(originalText);

        // Only update if the formatting actually changed something
        if (formattedText !== originalText) {
            // Use innerHTML to render the HTML tags
            message.innerHTML = formattedText;
        }
    });
};

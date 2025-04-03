/**
 * Airbnb Assistant Chat Application
 */
document.addEventListener('DOMContentLoaded', function() {
    // DOM elements
    const chatMessages = document.getElementById('chat-messages');
    const userInput = document.getElementById('user-input');
    const sendButton = document.getElementById('send-button');
    const themeToggle = document.getElementById('theme-toggle');

    // Session state
    let sessionId = null;
    let isWaitingForResponse = false;

    // Theme management
    function initTheme() {
        const storedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', storedTheme);
        updateThemeToggleText(storedTheme);
    }

    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';

        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeToggleText(newTheme);

        // Call server to update theme in session
        fetch('/toggle_theme', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({})
        }).catch(error => {
            console.error('Error updating theme on server:', error);
        });
    }

    function updateThemeToggleText(theme) {
        if (themeToggle) {
            themeToggle.textContent = theme === 'light' ? '🌙 Dark Mode' : '☀️ Light Mode';
        }
    }

    /**
     * Add a message to the chat UI
     */
    function addMessage(message, isUser = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = isUser ? 'message user' : 'message assistant';

        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';

        // For assistant messages, we'll use DOMPurify to sanitize HTML content
        if (!isUser && typeof DOMPurify !== 'undefined') {
            // Process the message to format JSON
            let processedMessage = message;

            // First check for JSON code blocks
            processedMessage = formatJSONInCodeBlocks(processedMessage);

            // Then check for raw JSON content (without code blocks)
            processedMessage = formatRawJSON(processedMessage);

            // Apply markdown using marked if available
            if (typeof marked !== 'undefined') {
                contentDiv.innerHTML = DOMPurify.sanitize(marked.parse(processedMessage));
            } else {
                contentDiv.innerHTML = DOMPurify.sanitize(processedMessage);
            }

            // Apply post-processing for any potential missed JSON
            setTimeout(() => {
                formatMissedJSON(contentDiv);
            }, 100);
        } else {
            // For user messages or if no sanitizer available, use text
            contentDiv.textContent = message;
        }

        messageDiv.appendChild(contentDiv);
        chatMessages.appendChild(messageDiv);

        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    /**
     * Format JSON inside code blocks
     */
    function formatJSONInCodeBlocks(text) {
        return text.replace(/```json\n([\s\S]*?)\n```/g, function(match, jsonText) {
            try {
                // Try to parse the JSON
                const data = JSON.parse(jsonText.trim());
                console.log("Parsed JSON data:", data);

                // Format based on content
                if (data.results) {
                    return generateListingsHTML(data.results);
                } else if (data.listing) {
                    return generateListingDetailHTML(data.listing);
                }

                // Generic JSON formatting if not a specific type
                return `<div class="formatted-json">${formatJSONAsHTML(data)}</div>`;
            } catch (e) {
                console.error("Error parsing JSON in code block:", e);
                // Return original if parsing fails
                return match;
            }
        });
    }

    /**
     * Try to detect and format raw JSON (not in code blocks)
     */
    function formatRawJSON(text) {
        // Look for patterns that suggest a JSON object/array
        const jsonRegex = /\{"\w+":[\s\S]*\}|\[\{"\w+":[\s\S]*\}\]/g;

        return text.replace(jsonRegex, function(match) {
            try {
                const data = JSON.parse(match);
                console.log("Parsed raw JSON data:", data);

                // Format based on content
                if (data.results) {
                    return generateListingsHTML(data.results);
                } else if (data.listing) {
                    return generateListingDetailHTML(data.listing);
                }

                // Generic JSON formatting if not a specific type
                return `<div class="formatted-json">${formatJSONAsHTML(data)}</div>`;
            } catch (e) {
                console.error("Error parsing raw JSON:", e);
                // Return original if parsing fails
                return match;
            }
        });
    }

    /**
     * Look for unformatted JSON in the DOM after rendering
     */
    function formatMissedJSON(element) {
        const textNodes = getTextNodesIn(element);

        textNodes.forEach(node => {
            const text = node.nodeValue;
            if (text && (text.includes('{"results":') || text.includes('{"listing":'))) {
                try {
                    // Try to extract and parse JSON
                    const jsonMatch = text.match(/(\{"\w+":[\s\S]*\})/);
                    if (jsonMatch) {
                        const jsonData = JSON.parse(jsonMatch[1]);

                        // Create new HTML element with formatted content
                        const newElement = document.createElement('div');

                        if (jsonData.results) {
                            newElement.innerHTML = generateListingsHTML(jsonData.results);
                        } else if (jsonData.listing) {
                            newElement.innerHTML = generateListingDetailHTML(jsonData.listing);
                        } else {
                            newElement.innerHTML = formatJSONAsHTML(jsonData);
                        }

                        // Replace the text node with our formatted HTML
                        if (node.parentNode) {
                            node.parentNode.replaceChild(newElement, node);
                        }
                    }
                } catch (e) {
                    console.error("Error formatting missed JSON:", e);
                }
            }
        });
    }

    /**
     * Get all text nodes within an element
     */
    function getTextNodesIn(element) {
        const textNodes = [];
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );

        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }

        return textNodes;
    }

    /**
     * Format JSON as HTML for generic JSON objects
     */
    function formatJSONAsHTML(data) {
        let html = '';

        if (Array.isArray(data)) {
            html = '<ul class="json-array">';
            data.forEach(item => {
                html += `<li>${formatJSONAsHTML(item)}</li>`;
            });
            html += '</ul>';
        } else if (typeof data === 'object' && data !== null) {
            html = '<dl class="json-object">';
            for (const [key, value] of Object.entries(data)) {
                html += `<dt>${key}:</dt><dd>${formatJSONAsHTML(value)}</dd>`;
            }
            html += '</dl>';
        } else {
            // Primitive value
            html = `<span class="json-value">${data}</span>`;
        }

        return html;
    }

    /**
     * Generate HTML for a list of listings
     */
    function generateListingsHTML(listings) {
        let html = '<div class="listings-container">';

        listings.forEach(listing => {
            html += generateListingCard(listing);
        });

        html += '</div>';
        return html;
    }

    /**
     * Generate HTML for a detailed listing view
     */
    function generateListingDetailHTML(listing) {
        // Use placeholder image if none provided
        const imageUrl = listing.image_url || 'https://via.placeholder.com/300x200?text=No+Image';

        // Format amenities as bullet points
        let amenitiesHtml = '';
        if (listing.amenities && listing.amenities.length > 0) {
            amenitiesHtml = '<h4>Amenities:</h4><ul class="listing-amenities-list">';
            listing.amenities.forEach(amenity => {
                amenitiesHtml += `<li>${amenity}</li>`;
            });
            amenitiesHtml += '</ul>';
        }

        // Format host info
        let hostHtml = '';
        if (listing.host) {
            hostHtml = `
                <div class="host-info">
                    <h4>Host Information:</h4>
                    <ul>
                        <li><strong>Name:</strong> ${listing.host.name}</li>
                        ${listing.host.superhost ? '<li><span class="superhost-badge">Superhost</span></li>' : ''}
                        <li><strong>Response Rate:</strong> ${listing.host.response_rate}%</li>
                        <li><strong>Member Since:</strong> ${listing.host.joined_date}</li>
                    </ul>
                </div>
            `;
        }

        // Format room details
        const roomDetails = `
            <div class="room-details">
                <h4>Room Details:</h4>
                <ul>
                    <li><strong>Bedrooms:</strong> ${listing.bedrooms}</li>
                    <li><strong>Bathrooms:</strong> ${listing.bathrooms}</li>
                    <li><strong>Max Guests:</strong> ${listing.max_guests}</li>
                </ul>
            </div>
        `;

        // Format pricing
        const pricingDetails = `
            <div class="pricing-details">
                <h4>Pricing:</h4>
                <p class="listing-price">$${listing.price_per_night} per night</p>
            </div>
        `;

        // Rating display
        const ratingHtml = listing.rating ?
            `<div class="listing-rating">
                <h4>Rating:</h4>
                <p><span class="star">★</span> ${listing.rating} (${listing.reviews_count || 0} reviews)</p>
            </div>` : '';

        // Put it all together in a card layout
        return `
            <div class="listing-detail-card">
                <h3 class="listing-title">${listing.title}</h3>
                <div class="listing-location">${listing.location}</div>

                <div class="listing-detail-content">
                    <div class="listing-image-container">
                        <img src="${imageUrl}" alt="${listing.title}" class="listing-detail-image">
                    </div>

                    <div class="listing-info">
                        <div class="listing-description">
                            <h4>Description:</h4>
                            <p>${listing.description || 'No description available'}</p>
                        </div>

                        ${roomDetails}
                        ${pricingDetails}
                        ${ratingHtml}
                        ${amenitiesHtml}
                        ${hostHtml}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Add a loading indicator to show the assistant is thinking
     */
    function addLoadingIndicator() {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message assistant';
        messageDiv.id = 'loading-indicator-message';

        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';

        const loadingSpinner = document.createElement('div');
        loadingSpinner.className = 'loading-indicator';

        const loadingText = document.createElement('span');
        loadingText.textContent = 'Thinking...';

        contentDiv.appendChild(loadingSpinner);
        contentDiv.appendChild(loadingText);
        messageDiv.appendChild(contentDiv);
        chatMessages.appendChild(messageDiv);

        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    /**
     * Remove the loading indicator once response is received
     */
    function removeLoadingIndicator() {
        const loadingMessage = document.getElementById('loading-indicator-message');
        if (loadingMessage) {
            loadingMessage.remove();
        }
    }

    /**
     * Safely parse JSON data from markdown codeblocks
     */
    function safeJSONParse(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Error parsing JSON:', e);
            return null;
        }
    }

    /**
     * Generate HTML for a listing card
     */
    function generateListingCard(listing, isDetailed = false) {
        // Use placeholder image if none provided
        const imageUrl = listing.image_url || 'https://via.placeholder.com/300x200?text=No+Image';

        // Format price
        const price = listing.price_per_night ? `$${listing.price_per_night} per night` : 'Price not available';

        // Format rating
        const rating = listing.rating ?
            `<div class="listing-rating"><span class="star">★</span> ${listing.rating} (${listing.reviews_count || 0} reviews)</div>` :
            '';

        // Format amenities
        let amenitiesHtml = '';
        if (listing.amenities && listing.amenities.length > 0) {
            amenitiesHtml = '<div class="listing-amenities">';
            const amenities = isDetailed ? listing.amenities : listing.amenities.slice(0, 3);
            amenities.forEach(amenity => {
                amenitiesHtml += `<span class="amenity-tag">${amenity}</span>`;
            });
            if (!isDetailed && listing.amenities.length > 3) {
                amenitiesHtml += `<span class="amenity-tag">+${listing.amenities.length - 3} more</span>`;
            }
            amenitiesHtml += '</div>';
        }

        // Generate detailed description if needed
        let detailedInfo = '';
        if (isDetailed && listing.description) {
            detailedInfo += `<p>${listing.description}</p>`;
        }

        // Generate host info if available
        let hostInfo = '';
        if (isDetailed && listing.host) {
            hostInfo = `
                <div class="host-info">
                    <h4>Host: ${listing.host.name}</h4>
                    ${listing.host.superhost ? '<span class="superhost-badge">Superhost</span>' : ''}
                    <p>Response rate: ${listing.host.response_rate}%</p>
                </div>
            `;
        }

        return `
            <div class="listing-card" data-id="${listing.id}">
                <img src="${imageUrl}" alt="${listing.title}" class="listing-image">
                <div class="listing-details">
                    <h3 class="listing-title">${listing.title}</h3>
                    <div class="listing-location">${listing.location}</div>
                    <div class="listing-price">${price}</div>
                    ${rating}
                    ${amenitiesHtml}
                    ${detailedInfo}
                    ${hostInfo}
                    <button class="view-details-btn" data-id="${listing.id}">
                        ${isDetailed ? 'Book Now' : 'View Details'}
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Send a message to the AI assistant
     */
    async function sendMessage(message) {
        if (isWaitingForResponse) {
            return;
        }

        // Add user message to UI
        addMessage(message, true);

        // Clear the input
        userInput.value = '';

        // Show loading indicator
        isWaitingForResponse = true;
        addLoadingIndicator();

        try {
            // Call the API
            const response = await fetch('/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    session_id: sessionId
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            // Get the response text and safely parse as JSON
            const responseText = await response.text();
            let data;

            try {
                data = JSON.parse(responseText);
            } catch (error) {
                console.error('Error parsing response JSON:', error);
                console.log('Raw response:', responseText);
                throw new Error('Invalid JSON response from server');
            }

            if (data.error) {
                throw new Error(data.error);
            }

            // Update session ID if it's new
            if (data.session_id) {
                sessionId = data.session_id;
            }

            // Remove loading indicator
            removeLoadingIndicator();

            // Add the response
            addMessage(data.response);

            // Set up event listeners for the listing cards
            setupListingCardListeners();

        } catch (error) {
            console.error('Error:', error);
            removeLoadingIndicator();
            addMessage('Sorry, I encountered an error: ' + error.message);
        } finally {
            isWaitingForResponse = false;
        }
    }

    /**
     * Set up event listeners for listing card buttons
     */
    function setupListingCardListeners() {
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', function() {
                const listingId = this.getAttribute('data-id');
                const buttonText = this.textContent.trim();

                if (buttonText === 'View Details') {
                    sendMessage(`Tell me more about listing ${listingId}`);
                }
            });
        });
    }

    // Event listeners
    if (sendButton) {
        sendButton.addEventListener('click', function() {
            const message = userInput.value.trim();
            if (message) {
                sendMessage(message);
            }
        });
    }

    if (userInput) {
        userInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const message = userInput.value.trim();
                if (message) {
                    sendMessage(message);
                }
            }
        });
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }

    // Initialize
    initTheme();

    // Focus on the input field
    if (userInput) {
        userInput.focus();
    }
});

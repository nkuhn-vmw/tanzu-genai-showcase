// Main application JavaScript for Movie Chatbot

document.addEventListener('DOMContentLoaded', function() {
    // Main chat elements (First Run)
    const chatContainer = document.getElementById('chatContainer');
    const userInput = document.getElementById('userInput');
    const sendButton = document.getElementById('sendButton');
    const processingContainer = document.getElementById('processingContainer');
    const progressBar = document.getElementById('progressBar');
    const processingMessage = document.getElementById('processingMessage');
    const locationInput = document.getElementById('locationInput');

    // Casual viewing elements
    const casualChatContainer = document.getElementById('casualChatContainer');
    const casualUserInput = document.getElementById('casualUserInput');
    const casualSendButton = document.getElementById('casualSendButton');
    const casualProcessingContainer = document.getElementById('casualProcessingContainer');
    const casualProgressBar = document.getElementById('casualProgressBar');
    const casualProcessingMessage = document.getElementById('casualProcessingMessage');

    // Tab elements
    const firstRunTab = document.getElementById('first-run-tab');
    const casualViewingTab = document.getElementById('casual-viewing-tab');

    // Genre select dropdown
    const genreSelect = document.getElementById('genreSelect');
    if (genreSelect) {
        genreSelect.addEventListener('change', function() {
            console.log('Genre select changed to:', this.value);
            switchGenreTab(this.value);
        });
    }

    // Set active tab based on localStorage if available
    const activeTab = localStorage.getItem('activeTab');
    if (activeTab === 'casual') {
        const casualTab = new bootstrap.Tab(casualViewingTab);
        casualTab.show();
        // Hide location field when in Casual Viewing mode
        document.querySelector('.location-input-group').style.display = 'none';
    } else {
        // Ensure location field is visible when in First Run mode (default)
        document.querySelector('.location-input-group').style.display = 'flex';
    }

    // Save active tab to localStorage and toggle location field visibility
    firstRunTab.addEventListener('shown.bs.tab', function() {
        localStorage.setItem('activeTab', 'first-run');
        // Show location field when in First Run mode
        document.querySelector('.location-input-group').style.display = 'flex';
    });

    casualViewingTab.addEventListener('shown.bs.tab', function() {
        localStorage.setItem('activeTab', 'casual');
        // Hide location field when in Casual Viewing mode
        document.querySelector('.location-input-group').style.display = 'none';
    });

    // Initial scroll to bottom of chat containers
    chatContainer.scrollTop = chatContainer.scrollHeight;
    casualChatContainer.scrollTop = casualChatContainer.scrollHeight;

    // Handle sending messages - each tab has its own conversation
    window.sendMessage = function(isFirstRun = true) {
        // Get elements based on active mode
        const activeInput = isFirstRun ? userInput : casualUserInput;
        const activeButton = isFirstRun ? sendButton : casualSendButton;
        const activeContainer = isFirstRun ? chatContainer : casualChatContainer;
        const activeProcessingContainer = isFirstRun ? processingContainer : casualProcessingContainer;
        const activeProgressBar = isFirstRun ? progressBar : casualProgressBar;
        const activeProcessingMessage = isFirstRun ? processingMessage : casualProcessingMessage;

        const message = activeInput.value.trim();
        
        // Only use location for First Run mode
        const location = isFirstRun ? document.getElementById('locationInput').value.trim() : "";

        if (message) {
            // Disable input and button while processing
            activeInput.disabled = true;
            activeButton.disabled = true;

            if (isFirstRun) {
                locationInput.disabled = true;
            }

            // Add user message to chat
            appendMessage(message, 'user', isFirstRun);

            // Clear input
            activeInput.value = '';

            // Show processing indicator with progress bar
            activeProcessingContainer.style.display = 'block';
            activeProgressBar.style.width = '0%';
            activeProcessingMessage.textContent = 'Processing your request...';

            // Start progress animation
            let progress = 0;
            const progressInterval = setInterval(() => {
                // Increment slowly up to 90% (we'll jump to 100% when done)
                if (progress < 90) {
                    progress += 5;
                    activeProgressBar.style.width = `${progress}%`;
                    activeProgressBar.setAttribute('aria-valuenow', progress);

                    // Update message based on progress
                    if (progress < 30) {
                        activeProcessingMessage.textContent = 'Looking for movies matching your request...';
                    } else if (progress < 60) {
                        activeProcessingMessage.textContent = 'Analyzing movie options...';
                    } else if (progress < 90) {
                        if (isFirstRun) {
                            activeProcessingMessage.textContent = 'Finding theaters near you...';
                        } else {
                            activeProcessingMessage.textContent = 'Preparing recommendations...';
                        }
                    }
                }
            }, 500);

            // Prepare request data
            const requestData = {
                message: message,
                first_run_filter: isFirstRun
            };

            // Add location if provided
            if (location) {
                requestData.location = location;
            }

            // Send to server
            fetch(SEND_MESSAGE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRFToken': getCookie('csrftoken')
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                // Complete the progress animation
                clearInterval(progressInterval);
                activeProgressBar.style.width = '100%';
                activeProgressBar.setAttribute('aria-valuenow', 100);
                activeProcessingMessage.textContent = 'Loading results...';

                // Slight delay to show the completed progress bar
                setTimeout(() => {
                    // Hide processing container
                    activeProcessingContainer.style.display = 'none';

                    if (data.status === 'success') {
                        // Add bot message to chat
                        appendMessage(data.message, 'bot', isFirstRun);

                        // Update recommendations if any
                        if (data.recommendations && data.recommendations.length > 0) {
                            if (isFirstRun) {
                                updateMovieGrid(data.recommendations);
                                updateShowtimesSection(data.recommendations);
                            } else {
                                updateCasualMovies(data.recommendations);
                            }
                        }
                    } else {
                        appendMessage('Sorry, there was an error processing your request. Please try again.', 'bot', isFirstRun);
                    }

                    // Re-enable all interactive elements
                    activeInput.disabled = false;
                    activeButton.disabled = false;

                    if (isFirstRun) {
                        locationInput.disabled = false;
                    }

                    // Focus input for next message
                    activeInput.focus();
                }, 500);
            })
            .catch(error => {
                // Stop the progress animation
                clearInterval(progressInterval);

                // Hide processing container
                activeProcessingContainer.style.display = 'none';

                console.error('Error:', error);
                appendMessage('Sorry, there was an error processing your request. Please try again.', 'bot', isFirstRun);

                // Re-enable all interactive elements
                activeInput.disabled = false;
                activeButton.disabled = false;

                if (isFirstRun) {
                    locationInput.disabled = false;
                }

                // Focus input for next message
                activeInput.focus();
            });
        }
    };

    // Add a message to the chat
    function appendMessage(text, sender, isFirstRun = true) {
        // Target the correct container
        const targetContainer = isFirstRun ? chatContainer : casualChatContainer;

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender === 'user' ? 'user-message' : 'bot-message'}`;

        // Apply formatting for bot messages, or set as plain text for user messages
        if (sender === 'bot') {
            messageDiv.innerHTML = formatMessageText(text);
        } else {
            messageDiv.textContent = text;
        }

        const timeSpan = document.createElement('span');
        timeSpan.className = `message-time ${sender === 'user' ? 'text-end' : ''}`;
        const now = new Date();
        timeSpan.textContent = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

        targetContainer.appendChild(messageDiv);
        targetContainer.appendChild(timeSpan);

        // Scroll to bottom of chat
        targetContainer.scrollTop = targetContainer.scrollHeight;
    }

    // Movie grid functions - using TMDB-style grid view
    window.updateMovieGrid = function(movies) {
        // First try to enhance movie posters with high-quality versions
        enhanceMoviePosters(movies, function(enhancedMovies) {
            // Use our new TMDB-style grid renderer with First Run mode
            renderTMDBGrid(enhancedMovies, 'movieGridContainer', true);

            // Also update the theaters section since we've got new movies
            updateShowtimesSection(enhancedMovies);
        });
    }

    // Update the casual movies view
    window.updateCasualMovies = function(movies) {
        // Enhance posters and then render
        enhanceMoviePosters(movies, function(enhancedMovies) {
            // Use the same renderer for consistency, but in Casual mode
            renderTMDBGrid(enhancedMovies, 'casualMovieContainer', false);
        });
    }

    // Update the showtimes section
    window.updateShowtimesSection = function(movies) {
        const theatersContainer = document.getElementById('theatersContainer');
        const dateTabsContainer = document.getElementById('dateTabs');

        // Count how many movies are current releases
        const currentReleaseCount = movies.filter(movie => movie.is_current_release === true).length;
        const moviesWithTheatersCount = movies.filter(movie =>
            movie.is_current_release === true &&
            movie.theaters &&
            movie.theaters.length > 0
        ).length;

        // Only display for current movies with theaters
        const currentMovies = movies.filter(movie =>
            movie.is_current_release === true &&
            movie.theaters &&
            movie.theaters.length > 0
        );

        // Log information for debugging
        console.log(`Total movies: ${movies.length}`);
        console.log(`Current release movies: ${currentReleaseCount}`);
        console.log(`Movies with theaters: ${moviesWithTheatersCount}`);

        // If no current movies with theaters, show appropriate message
        if (currentMovies.length === 0) {
            if (currentReleaseCount === 0) {
                theatersContainer.innerHTML = '<p class="text-muted">No current release movies found. Try searching for movies currently playing in theaters.</p>';
            } else {
                theatersContainer.innerHTML = '<p class="text-muted">No theaters near you are currently showing the recommended movies.</p>';
            }
            dateTabsContainer.innerHTML = '';
            return;
        }

        // Generate date tabs for the next 7 days
        dateTabsContainer.innerHTML = '';
        const dates = [];
        const today = new Date();

        for (let i = 0; i < 7; i++) {
            const date = new Date(today);
            date.setDate(today.getDate() + i);
            dates.push(date);

            // Format day name and date
            const dayName = i === 0 ? 'Today' : date.toLocaleDateString('en-US', { weekday: 'short' });
            const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

            // Create tab button
            const tabButton = document.createElement('button');
            tabButton.type = 'button';
            tabButton.className = `date-tab ${i === 0 ? 'active' : ''}`;
            tabButton.setAttribute('data-date-index', i);
            tabButton.innerHTML = `
                <div class="small">${dayName}</div>
                <div>${dateStr}</div>
            `;

            // Add click handler to switch date
            tabButton.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.date-tab').forEach(btn => {
                    btn.classList.remove('active');
                });

                // Add active class to clicked tab
                this.classList.add('active');

                // Update showtimes display for this date
                displayShowtimesForDate(currentMovies, parseInt(this.getAttribute('data-date-index')));
            });

            dateTabsContainer.appendChild(tabButton);
        }

        // Display showtimes for today (index 0) initially
        displayShowtimesForDate(currentMovies, 0);
    }

    // Display showtimes for a specific date
    function displayShowtimesForDate(movies, dateIndex) {
        const theatersContainer = document.getElementById('theatersContainer');
        theatersContainer.innerHTML = '';

        // Get all theaters across all movies
        const allTheaters = new Map();

        movies.forEach(movie => {
            if (movie.theaters) {
                // Debug the theaters data structure
                console.log(`Processing theaters for ${movie.title}:`, movie.theaters);

                movie.theaters.forEach(theater => {
                    // If theater not in map yet, add it
                    if (!allTheaters.has(theater.name)) {
                        allTheaters.set(theater.name, {
                            name: theater.name,
                            address: theater.address,
                            distance_miles: theater.distance_miles || 5.0, // Default if distance not available
                            movies: {}
                        });
                    }

                    // Add movie and its showtimes to this theater
                    const theaterEntry = allTheaters.get(theater.name);

                    // Check if the theater has showtimes array (new structure) or direct showtime data (old structure)
                    let showtimesToProcess = [];
                    if (Array.isArray(theater.showtimes)) {
                        showtimesToProcess = theater.showtimes;
                    } else if (theater.start_time) {
                        // Legacy format - single showtime directly on theater object
                        showtimesToProcess = [{
                            start_time: theater.start_time,
                            format: theater.format || 'Standard'
                        }];
                    }

                    // Only process if we have showtimes to work with
                    if (showtimesToProcess.length > 0) {
                        // Group showtimes by format (Standard, IMAX, 3D, etc.)
                        const showtimesByFormat = {};

                        showtimesToProcess.forEach(showtime => {
                            const format = showtime.format || 'Standard';
                            if (!showtimesByFormat[format]) {
                                showtimesByFormat[format] = [];
                            }

                            // Add showtime to appropriate format
                            if (showtime.start_time) {
                                showtimesByFormat[format].push(showtime.start_time);
                            }
                        });

                        // Only add to theater's movies if we have actual showtimes
                        if (Object.keys(showtimesByFormat).length > 0) {
                            // Add to theater's movies
                            theaterEntry.movies[movie.title] = {
                                id: movie.tmdb_id,
                                title: movie.title,
                                showtimesByFormat: showtimesByFormat
                            };
                        }
                    }
                });
            }
        });

        // Filter and sort theaters by distance
        const sortedTheaters = Array.from(allTheaters.values())
            .sort((a, b) => (a.distance_miles || 0) - (b.distance_miles || 0));

        // Display each theater
        sortedTheaters.forEach(theater => {
            const theaterSection = document.createElement('div');
            theaterSection.className = 'theater-section mb-3';

            // Format distance
            const distanceStr = theater.distance_miles ?
                `${theater.distance_miles.toFixed(1)} mi · ` : '';

            // Create theater header
            const theaterHeader = document.createElement('div');
            theaterHeader.className = 'theater-header';
            theaterHeader.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">${theater.name}</h5>
                        <small>${distanceStr}${theater.address || ''}</small>
                    </div>
                </div>
            `;

            // Create theater body
            const theaterBody = document.createElement('div');
            theaterBody.className = 'theater-body';

            // For each movie at this theater
            Object.values(theater.movies).forEach(movie => {
                theaterBody.innerHTML += `<div class="fw-bold mb-2">${movie.title}</div>`;

                // For each format (Standard, IMAX, etc)
                Object.entries(movie.showtimesByFormat).forEach(([format, times]) => {
                    // Filter showtimes for the selected date
                    const today = new Date();
                    const selectedDate = new Date(today);
                    selectedDate.setDate(today.getDate() + dateIndex);

                    // Format date for comparison (YYYY-MM-DD)
                    const selectedDateStr = selectedDate.toISOString().split('T')[0];

                    // Filter times for the selected date
                    const timesForDate = times.filter(timeStr => {
                        const date = new Date(timeStr);
                        return date.toISOString().split('T')[0] === selectedDateStr;
                    });

                    // Only display if there are times for this date
                    if (timesForDate.length > 0) {
                        const formatDiv = document.createElement('div');
                        formatDiv.className = 'mb-3';
                        formatDiv.innerHTML = `<div class="small text-muted mb-1">${format}</div>`;

                        const showtimesDiv = document.createElement('div');
                        showtimesDiv.className = 'd-flex flex-wrap gap-2';

                        // Sort and format times
                        timesForDate
                            .map(timeStr => new Date(timeStr))
                            .sort((a, b) => a - b) // Sort chronologically
                            .forEach(date => {
                                // Format as 24-hour time (HH:MM)
                                const timeStr = date.toLocaleTimeString([], {
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    hour12: false
                                });

                                // Create showtime badge with appropriate class based on format
                                const badgeClass = format.toLowerCase().includes('imax') ? 'imax' :
                                                  format.toLowerCase().includes('3d') ? 'threed' : '';

                                const badge = document.createElement('span');
                                badge.className = `showtime-badge ${badgeClass}`;
                                badge.textContent = timeStr;

                                showtimesDiv.appendChild(badge);
                            });

                        formatDiv.appendChild(showtimesDiv);
                        theaterBody.appendChild(formatDiv);
                    }
                });
            });

            // Add header and body to section
            theaterSection.appendChild(theaterHeader);
            theaterSection.appendChild(theaterBody);

            // Add to container
            theatersContainer.appendChild(theaterSection);
        });

        // If no theaters have showtimes for the selected date
        if (sortedTheaters.length === 0) {
            theatersContainer.innerHTML = `
                <p class="text-muted">No theaters are showing the recommended movies on this date.</p>
            `;
        }
    }

    // Get CSRF token for AJAX request
    window.getCookie = function(name) {
        let cookieValue = null;
        if (document.cookie && document.cookie !== '') {
            const cookies = document.cookie.split(';');
            for (let i = 0; i < cookies.length; i++) {
                const cookie = cookies[i].trim();
                if (cookie.substring(0, name.length + 1) === (name + '=')) {
                    cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
                    break;
                }
            }
        }
        return cookieValue;
    }

    // Event listeners
    sendButton.addEventListener('click', () => sendMessage(true));
    casualSendButton.addEventListener('click', () => sendMessage(false));

    userInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            sendMessage(true);
            event.preventDefault();
        }
    });

    casualUserInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            sendMessage(false);
            event.preventDefault();
        }
    });

    // Try to get user location automatically
    function detectUserLocation() {
        // Show the processing indicator
        processingMessage.textContent = "Detecting your location...";
        processingContainer.style.display = 'block';
        progressBar.style.width = '50%';

        console.log("Starting location detection");

        // Function to hide the processing indicator
        function hideProcessing(message = null) {
            if (message) {
                processingMessage.textContent = message;
                setTimeout(() => {
                    processingContainer.style.display = 'none';
                }, 2000);
            } else {
                processingContainer.style.display = 'none';
            }
        }

        // Function to set a default location
        function useDefaultLocation() {
            console.log("Using default location (Seattle)");
            locationInput.value = "Seattle, WA";
            hideProcessing("Using default location: Seattle, WA");
        }

        // Try to use the browser's geolocation API
        if (navigator.geolocation) {
            console.log("Geolocation API available, requesting position");
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    // Success - we have coordinates, now reverse geocode to get address
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;
                    console.log(`Got coordinates: ${latitude}, ${longitude}`);

                    // Use reverse geocoding to get readable location
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`, {
                        headers: {
                            'User-Agent': 'Movie Chatbot Application'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log("Received geocoding response:", data);
                        
                        // Extract a reasonable location name
                        let locationName;
                        if (data.display_name) {
                            // Get first three parts of the address
                            locationName = data.display_name.split(',').slice(0, 3).join(',');
                        } else if (data.address) {
                            // Try to use city, state, country
                            const address = data.address;
                            const city = address.city || address.town || address.village || address.hamlet;
                            const state = address.state || address.province;
                            const country = address.country;
                            
                            if (city && state) {
                                locationName = `${city}, ${state}`;
                            } else if (city) {
                                locationName = city;
                            }
                        }

                        // If we have a location name, use it
                        if (locationName) {
                            console.log(`Setting location to: ${locationName}`);
                            locationInput.value = locationName;
                            hideProcessing(`Location detected: ${locationName}`);
                        } else {
                            console.error("Could not parse location from response:", data);
                            useDefaultLocation();
                        }
                    })
                    .catch(error => {
                        console.error("Error with reverse geocoding:", error);
                        useDefaultLocation();
                    });
                },
                function(error) {
                    console.error(`Geolocation error (${error.code}): ${error.message}`);
                    useDefaultLocation();
                },
                {
                    enableHighAccuracy: true, // Try for best accuracy
                    timeout: 10000, // 10 second timeout
                    maximumAge: 5 * 60 * 1000 // 5 minutes cache
                }
            );
        } else {
            // Browser doesn't support geolocation
            console.error("Geolocation not supported by this browser");
            useDefaultLocation();
        }
    }

    // Make sample question functions available globally
    window.sendSampleQuestion = function(question) {
        // Wait for DOM to be ready
        setTimeout(() => {
            const userInput = document.getElementById('userInput');
            userInput.value = question;
            // Focus the input to show the user the text was added
            userInput.focus();
            // Send the message
            window.sendMessage(true); // true for first run mode
        }, 0);
    };

    window.sendCasualSampleQuestion = function(question) {
        // Wait for DOM to be ready
        setTimeout(() => {
            const casualUserInput = document.getElementById('casualUserInput');
            casualUserInput.value = question;
            // Focus the input to show the user the text was added
            casualUserInput.focus();
            // Send the message
            window.sendMessage(false); // false for casual viewing mode
        }, 0);
    };

    // Detect location on page load
    detectUserLocation();

    // Focus input on page load
    userInput.focus();

    // Initialize genre tabs - ensure popular tab is visible on load
    switchGenreTab('popular');
});

/**
 * TMDB-inspired movie grid for displaying movie recommendations
 */

// Enhanced movie grid rendering function
window.renderTMDBGrid = function(movies, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = '';

    // If no movies to display
    if (!movies || movies.length === 0) {
        container.innerHTML = '<p class="text-muted">No movies match your criteria. Try another search.</p>';
        return;
    }

    // Create a grid container
    const movieGrid = document.createElement('div');
    movieGrid.className = 'movie-grid';

    // Create a card for each movie
    movies.forEach(movie => {
        // Format the release year
        let releaseYear = '';
        if (movie.release_date) {
            try {
                const releaseDate = new Date(movie.release_date);
                releaseYear = releaseDate.getFullYear();
            } catch (e) {
                console.error("Error parsing release date", e);
            }
        }

        // Get the best poster URL available or use a placeholder
        // Check for enhanced poster URLs first
        let posterUrl = '';
        
        // If we have poster URLs at different sizes, use the appropriate one
        if (movie.poster_urls && movie.poster_urls.large) {
            posterUrl = movie.poster_urls.large;
        } else if (movie.poster_urls && movie.poster_urls.original) {
            posterUrl = movie.poster_urls.original;
        } else if (movie.backdrop_url) {
            // Use backdrop as fallback
            posterUrl = movie.backdrop_url;
        } else {
            // Use standard poster URL or placeholder
            posterUrl = movie.poster_url || 'https://via.placeholder.com/300x450?text=No+Poster';
        }

        // Create the movie card
        const movieCard = document.createElement('div');
        movieCard.className = 'movie-grid-card';

        // Add "Now Playing" badge if it's a current release
        if (movie.is_current_release) {
            const badge = document.createElement('div');
            badge.className = 'movie-grid-badge';
            badge.textContent = 'Now Playing';
            movieCard.appendChild(badge);
        }

        // Add the poster image
        const posterImg = document.createElement('img');
        posterImg.src = posterUrl;
        posterImg.alt = `${movie.title} poster`;
        posterImg.loading = 'lazy'; // Lazy load images
        movieCard.appendChild(posterImg);

        // Add movie info section
        const infoDiv = document.createElement('div');
        infoDiv.className = 'movie-grid-info';

        // Add title
        const titleDiv = document.createElement('div');
        titleDiv.className = 'movie-grid-title';
        titleDiv.textContent = movie.title;
        infoDiv.appendChild(titleDiv);

        // Add year if available
        if (releaseYear) {
            const yearDiv = document.createElement('div');
            yearDiv.className = 'movie-grid-year';
            yearDiv.textContent = releaseYear;
            infoDiv.appendChild(yearDiv);
        }

        // Add rating stars if available
        if (movie.rating) {
            const ratingDiv = document.createElement('div');
            ratingDiv.className = 'movie-grid-rating';

            // Calculate rating stars (0-10 scale to 0-5 scale)
            const ratingStars = Math.round(movie.rating / 2);
            ratingDiv.innerHTML = '★'.repeat(ratingStars) + '☆'.repeat(5 - ratingStars);
            infoDiv.appendChild(ratingDiv);
        }

        // Add movie explanation or description with word limit
        if (movie.explanation || movie.overview) {
            const descDiv = document.createElement('div');
            descDiv.className = 'movie-grid-description';
            
            // Get the description text, preferring explanation over overview
            let descriptionText = movie.explanation || movie.overview || '';
            
            // Limit to 30 words and add ellipsis if needed
            const words = descriptionText.split(/\s+/);
            if (words.length > 30) {
                descriptionText = words.slice(0, 30).join(' ') + '...';
            }
            
            descDiv.textContent = descriptionText;
            infoDiv.appendChild(descDiv);
        }

        // Add info div to card
        movieCard.appendChild(infoDiv);

        // Add the card to the grid
        movieGrid.appendChild(movieCard);
    });

    // Add the grid to the container
    container.appendChild(movieGrid);
};

// Helper function to fetch high-quality images for movies
window.enhanceMoviePosters = function(movies, callback) {
    let enhancedMovies = [...movies]; // Create a copy to modify
    let pendingRequests = 0;
    let allRequestsComplete = false;

    // Process each movie to get better poster images
    enhancedMovies.forEach((movie, index) => {
        // Only process movies that have a TMDB ID
        if (movie.tmdb_id) {
            pendingRequests++;

            // Define API endpoint for movie details
            const endpoint = `https://api.themoviedb.org/3/movie/${movie.tmdb_id}?api_key=YOUR_API_KEY&append_to_response=images`;

            // Simulate API call - in a real implementation, this would be a fetch request
            // Here we'll just simulate a success response after a short delay
            setTimeout(() => {
                pendingRequests--;

                // Enhance the poster URL with a higher quality version if available
                // This is just for simulation - in a real implementation, you'd use data from the API response
                if (movie.poster_url && movie.poster_url.includes('image.tmdb.org')) {
                    // Replace w500 with original for higher quality
                    enhancedMovies[index].poster_url = movie.poster_url.replace('/w500/', '/original/');
                }

                // Check if all requests are complete
                if (pendingRequests === 0 && allRequestsComplete) {
                    callback(enhancedMovies);
                }
            }, 100);
        }
    });

    // Mark all requests as initiated
    allRequestsComplete = true;

    // If no requests were made, call the callback immediately
    if (pendingRequests === 0) {
        callback(enhancedMovies);
    }
};

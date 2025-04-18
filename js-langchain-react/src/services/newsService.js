import axios from 'axios';

/**
 * Determine if we're running in development mode by checking
 * if we're accessing the app from localhost on the React dev server port
 */
function isLocalDevelopment() {
  return window.location.hostname === 'localhost' &&
         (window.location.port === '3000' || window.location.port === '3002');
}

// In development, use the full URL with the API port
// In production, use relative URLs (empty base path)
const API_BASE_URL = isLocalDevelopment()
  ? (process.env.REACT_APP_API_BASE_URL || 'http://localhost:3001')
  : '';

/**
 * Search for news articles on a specific topic
 * @param {string} topic - The topic to search for
 * @returns {Promise<{articles: Array}>} - Promise with array of article objects
 */
export const searchNews = async (topic) => {
  try {
    // In development: full URL with port (http://localhost:3001/api/news)
    // In production: relative URL (/api/news)
    const response = await axios.get(`${API_BASE_URL}/api/news`, {
      params: { topic }
    });

    return response.data;
  } catch (error) {
    console.error('Error in searchNews service:', error);
    throw error;
  }
};

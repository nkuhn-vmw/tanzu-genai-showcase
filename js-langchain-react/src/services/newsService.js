import axios from 'axios';

// Get the API base URL from environment variables or use default
const API_BASE_URL = process.env.REACT_APP_API_BASE_URL || 'http://localhost:3001';

/**
 * Search for news articles on a specific topic
 * @param {string} topic - The topic to search for
 * @returns {Promise<{articles: Array}>} - Promise with array of article objects
 */
export const searchNews = async (topic) => {
  try {
    const response = await axios.get(`${API_BASE_URL}/api/news`, {
      params: { topic }
    });

    return response.data;
  } catch (error) {
    console.error('Error in searchNews service:', error);
    throw error;
  }
};

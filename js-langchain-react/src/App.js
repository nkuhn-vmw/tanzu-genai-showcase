import React, { useState } from 'react';
import './App.css';
import NewsSearch from './components/NewsSearch';
import NewsList from './components/NewsList';
import { searchNews } from './services/newsService';

function App() {
  const [articles, setArticles] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [searchPerformed, setSearchPerformed] = useState(false);

  const handleSearch = async (topic) => {
    setLoading(true);
    setError(null);
    setSearchPerformed(true);

    try {
      const result = await searchNews(topic);
      setArticles(result.articles);
    } catch (err) {
      setError('Failed to fetch news. Please try again later.');
      console.error('Error fetching news:', err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="App">
      <header className="App-header">
        <h1>News Aggregator</h1>
        <p>Search for news on any topic and get AI-summarized results</p>
      </header>
      <main className="App-main">
        <NewsSearch onSearch={handleSearch} />

        {loading && <div className="loading">Loading news articles...</div>}

        {error && <div className="error">{error}</div>}

        {!loading && !error && searchPerformed && articles.length === 0 && (
          <div className="no-results">No articles found. Try a different topic.</div>
        )}

        {!loading && !error && articles.length > 0 && (
          <NewsList articles={articles} />
        )}
      </main>
      <footer className="App-footer">
        <p>
          Powered by LangChain and GenAI on Tanzu Platform for Cloud Foundry
        </p>
      </footer>
    </div>
  );
}

export default App;

import React, { useState } from 'react';
import './NewsSearch.css';

function NewsSearch({ onSearch }) {
  const [topic, setTopic] = useState('');

  const handleSubmit = (e) => {
    e.preventDefault();
    if (topic.trim()) {
      onSearch(topic);
    }
  };

  return (
    <div className="news-search">
      <form onSubmit={handleSubmit}>
        <input
          type="text"
          value={topic}
          onChange={(e) => setTopic(e.target.value)}
          placeholder="Enter a topic (e.g., climate change, technology, sports)"
          required
        />
        <button type="submit">Search</button>
      </form>
    </div>
  );
}

export default NewsSearch;

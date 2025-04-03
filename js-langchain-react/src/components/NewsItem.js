import React from 'react';
import './NewsItem.css';

function NewsItem({ article }) {
  const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
  };

  return (
    <div className="news-item">
      {article.imageUrl && (
        <div className="news-item-image">
          <img src={article.imageUrl} alt={article.title} />
        </div>
      )}
      <div className="news-item-content">
        <h2 className="news-item-title">
          <a href={article.url} target="_blank" rel="noopener noreferrer">
            {article.title}
          </a>
        </h2>
        <div className="news-item-meta">
          <span className="news-item-source">{article.source}</span>
          <span className="news-item-date">{formatDate(article.publishedAt)}</span>
        </div>
        <p className="news-item-summary">{article.summary}</p>
        <a 
          href={article.url} 
          className="news-item-link" 
          target="_blank" 
          rel="noopener noreferrer"
        >
          Read full article
        </a>
      </div>
    </div>
  );
}

export default NewsItem;

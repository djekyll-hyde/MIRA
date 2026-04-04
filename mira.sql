-- Research Paper Repository KMS
-- Run this once in phpMyAdmin to create all tables

CREATE DATABASE IF NOT EXISTS mira;
USE mira;

-- 1. Users table
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(100) NOT NULL,
    role          ENUM('student', 'lecturer', 'admin') DEFAULT 'student',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Papers table
CREATE TABLE IF NOT EXISTS papers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(500) NOT NULL,
    authors       VARCHAR(500),
    abstract      TEXT,
    file_path     VARCHAR(300) NOT NULL,
    uploaded_by   INT NOT NULL,
    uploaded_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Paper chunks (heart of the RAG chatbot)
CREATE TABLE IF NOT EXISTS paper_chunks (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    paper_id       INT NOT NULL,
    chunk_index    INT NOT NULL,
    content        TEXT NOT NULL,
    embedding_json MEDIUMTEXT,
    FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
);

-- 4. Tags
CREATE TABLE IF NOT EXISTS tags (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- 5. Paper tags (links papers to tags)
CREATE TABLE IF NOT EXISTS paper_tags (
    paper_id INT NOT NULL,
    tag_id   INT NOT NULL,
    PRIMARY KEY (paper_id, tag_id),
    FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)   REFERENCES tags(id)   ON DELETE CASCADE
);

-- 6. Summaries (AI generated)
CREATE TABLE IF NOT EXISTS summaries (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    paper_id INT NOT NULL UNIQUE,
    content  TEXT NOT NULL,
    FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
);

-- 7. Conversations (chat sessions)
CREATE TABLE IF NOT EXISTS conversations (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    paper_id   INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (paper_id) REFERENCES papers(id) ON DELETE CASCADE
);

-- 8. Messages (individual chat messages)
CREATE TABLE IF NOT EXISTS messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    conv_id    INT NOT NULL,
    role       ENUM('user', 'assistant') NOT NULL,
    content    TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conv_id) REFERENCES conversations(id) ON DELETE CASCADE
);
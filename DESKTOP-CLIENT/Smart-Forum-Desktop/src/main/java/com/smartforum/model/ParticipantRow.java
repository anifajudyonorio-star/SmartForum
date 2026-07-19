package com.smartforum.model;

public class ParticipantRow {
    private final String name;
    private final int topics;
    private final int posts;
    private final int replies;
    private final int score;

    public ParticipantRow(String name, int topics, int posts, int replies, int score) {
        this.name = name;
        this.topics = topics;
        this.posts = posts;
        this.replies = replies;
        this.score = score;
    }

    public String getName() {
        return name;
    }

    public int getTopics() {
        return topics;
    }

    public int getPosts() {
        return posts;
    }

    public int getReplies() {
        return replies;
    }

    public int getScore() {
        return score;
    }
}

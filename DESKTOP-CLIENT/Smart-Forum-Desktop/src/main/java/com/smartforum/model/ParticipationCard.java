package com.smartforum.model;

public class ParticipationCard {
    private final String name;
    private final int topics;
    private final int posts;
    private final int replies;
    private final int score;
    private final String rank;
    private final int progress;

    public ParticipationCard(String name, int topics, int posts, int replies, int score, String rank, int progress) {
        this.name = name;
        this.topics = topics;
        this.posts = posts;
        this.replies = replies;
        this.score = score;
        this.rank = rank;
        this.progress = progress;
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

    public String getRank() {
        return rank;
    }

    public int getProgress() {
        return progress;
    }

    public String getInitials() {
        if (name == null || name.isBlank()) {
            return "??";
        }
        return name.substring(0, Math.min(2, name.length())).toUpperCase();
    }
}

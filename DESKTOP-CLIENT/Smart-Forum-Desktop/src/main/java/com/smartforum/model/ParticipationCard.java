package com.smartforum.model;

public class ParticipationCard {
    private final String name;
    private final int topics;
    private final int posts;
    private final int replies;
    private final int score;
    private final String rank;
    private final int progress;
    private final int autoScore;
    private final int manualMarks;

    public ParticipationCard(
            String name,
            int topics,
            int posts,
            int replies,
            int score,
            String rank,
            int progress) {
        this(name, topics, posts, replies, score, rank, progress, score, 0);
    }

    public ParticipationCard(
            String name,
            int topics,
            int posts,
            int replies,
            int score,
            String rank,
            int progress,
            int autoScore,
            int manualMarks) {
        this.name = name;
        this.topics = topics;
        this.posts = posts;
        this.replies = replies;
        this.score = score;
        this.rank = rank;
        this.progress = progress;
        this.autoScore = autoScore;
        this.manualMarks = manualMarks;
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

    public int getAutoScore() {
        return autoScore;
    }

    public int getManualMarks() {
        return manualMarks;
    }

    public String getInitials() {
        if (name == null || name.isBlank()) {
            return "??";
        }
        return name.substring(0, Math.min(2, name.length())).toUpperCase();
    }
}

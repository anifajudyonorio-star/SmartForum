package com.smartforum.model;

public class Quiz {

    private int id;
    private int categoryId;
    private String categoryName;
    private String title;
    private String description;
    private int duration;
    private int totalMarks;
    private int participationMarks;
    private String startDate;
    private String endDate;
    private int questionsCount;
    private int maximumMarks;
    private String groupName;
    private String lifecycleStatus;
    private String statusLabel;
    private boolean completed;
    private boolean canStart;
    private boolean canPublish;
    private boolean canDelete;
    private boolean published;

    public Quiz() {}

    public Quiz(int id, int categoryId, String categoryName, String title,
                String description, int duration, int totalMarks,
                String startDate, String endDate) {
        this.id = id;
        this.categoryId = categoryId;
        this.categoryName = categoryName;
        this.title = title;
        this.description = description;
        this.duration = duration;
        this.totalMarks = totalMarks;
        this.startDate = startDate;
        this.endDate = endDate;
    }

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getCategoryId() { return categoryId; }
    public void setCategoryId(int categoryId) { this.categoryId = categoryId; }

    public String getCategoryName() { return categoryName; }
    public void setCategoryName(String categoryName) { this.categoryName = categoryName; }

    public String getTitle() { return title; }
    public void setTitle(String title) { this.title = title; }

    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }

    public int getDuration() { return duration; }
    public void setDuration(int duration) { this.duration = duration; }

    public int getTotalMarks() { return totalMarks; }
    public void setTotalMarks(int totalMarks) { this.totalMarks = totalMarks; }

    public int getParticipationMarks() { return participationMarks; }
    public void setParticipationMarks(int participationMarks) { this.participationMarks = participationMarks; }

    public String getStartDate() { return startDate; }
    public void setStartDate(String startDate) { this.startDate = startDate; }

    public String getEndDate() { return endDate; }
    public void setEndDate(String endDate) { this.endDate = endDate; }

    public int getQuestionsCount() { return questionsCount; }
    public void setQuestionsCount(int questionsCount) { this.questionsCount = questionsCount; }

    public int getMaximumMarks() { return maximumMarks; }
    public void setMaximumMarks(int maximumMarks) { this.maximumMarks = maximumMarks; }

    public String getGroupName() { return groupName; }
    public void setGroupName(String groupName) { this.groupName = groupName; }

    public String getLifecycleStatus() { return lifecycleStatus; }
    public void setLifecycleStatus(String lifecycleStatus) { this.lifecycleStatus = lifecycleStatus; }

    public String getStatusLabel() { return statusLabel; }
    public void setStatusLabel(String statusLabel) { this.statusLabel = statusLabel; }

    public boolean isCompleted() { return completed; }
    public void setCompleted(boolean completed) { this.completed = completed; }

    public boolean isCanStart() { return canStart; }
    public void setCanStart(boolean canStart) { this.canStart = canStart; }

    public boolean isCanPublish() { return canPublish; }
    public void setCanPublish(boolean canPublish) { this.canPublish = canPublish; }

    public boolean isCanDelete() { return canDelete; }
    public void setCanDelete(boolean canDelete) { this.canDelete = canDelete; }

    public boolean isPublished() { return published; }
    public void setPublished(boolean published) { this.published = published; }

    @Override
    public String toString() { return title; }
}

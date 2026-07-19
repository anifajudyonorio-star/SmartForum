package com.smartforum.controller;

import com.smartforum.model.GroupMember;
import com.smartforum.model.Post;
import com.smartforum.model.Topic;
import com.smartforum.service.GroupService;
import com.smartforum.service.PostService;
import com.smartforum.service.TopicService;

import java.util.List;
import java.util.Optional;

/**
 * Mirrors web {@code PostController} — store, edit, update, destroy for topic chat posts.
 */
public class PostController {

    private final PostService postService = PostService.getInstance();
    private final TopicService topicService = TopicService.getInstance();
    private final GroupService groupService = GroupService.getInstance();

    public boolean canParticipate(int topicId) {
        return postService.canParticipateInTopic(topicId);
    }

    public List<Post> loadPosts(int topicId) {
        return postService.getPosts(topicId);
    }

    /** Web: store(Request, Topic) */
    public Post store(int topicId, String content, Integer parentPostId, List<Integer> excludedUserIds) {
        String trimmed = content == null ? "" : content.trim();
        if (trimmed.isBlank()) {
            throw new IllegalArgumentException("Message content is required.");
        }
        return postService.store(topicId, trimmed, parentPostId, excludedUserIds);
    }

    /** Web: edit(Post) */
    public Optional<PostEditContext> edit(int postId) {
        if (!postService.canManagePost(postId)) {
            return Optional.empty();
        }

        Post post = postService.getPost(postId).orElse(null);
        if (post == null) {
            return Optional.empty();
        }

        Topic topic = topicService.getTopic(post.getTopicId()).orElse(null);
        if (topic == null) {
            return Optional.empty();
        }

        List<GroupMember> members = topicService.getMembersForExclude(post.getTopicId());
        return Optional.of(new PostEditContext(post, topic, members, post.getHiddenFromUserIds()));
    }

    /** Web: update(Request, Post) */
    public Post update(int postId, String content, List<Integer> excludedUserIds) {
        String trimmed = content == null ? "" : content.trim();
        if (trimmed.isBlank()) {
            throw new IllegalArgumentException("Message content is required.");
        }
        return postService.update(postId, trimmed, excludedUserIds);
    }

    /** Web: destroy(Post) */
    public void destroy(int postId) {
        postService.destroy(postId);
    }

    public boolean canManagePost(int postId) {
        return postService.canManagePost(postId);
    }

    public record PostEditContext(
            Post post,
            Topic topic,
            List<GroupMember> groupMembers,
            List<Integer> excludedUserIds
    ) {
    }
}

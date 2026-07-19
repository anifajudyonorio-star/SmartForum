package com.smartforum.service;

import com.smartforum.model.GroupMember;

import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.stream.Collectors;

/**
 * Mirrors web {@code PostVisibilityService} — validates and syncs post exclusions.
 */
public final class PostVisibilityService {
    private static final PostVisibilityService INSTANCE = new PostVisibilityService();

    private PostVisibilityService() {
    }

    public static PostVisibilityService getInstance() {
        return INSTANCE;
    }

    public List<Integer> resolveExcludedUserIds(int groupId, int authorId, List<Integer> excludedUserIds) {
        if (excludedUserIds == null || excludedUserIds.isEmpty()) {
            return List.of();
        }

        Set<Integer> memberIds = GroupService.getInstance().getMembers(groupId).stream()
                .map(GroupMember::getUserId)
                .collect(Collectors.toCollection(HashSet::new));

        List<Integer> valid = new ArrayList<>();
        Set<Integer> seen = new HashSet<>();
        for (Integer rawId : excludedUserIds) {
            if (rawId == null) {
                continue;
            }
            int id = rawId;
            if (id <= 0 || id == authorId || !memberIds.contains(id) || !seen.add(id)) {
                continue;
            }
            valid.add(id);
        }
        return valid;
    }
}

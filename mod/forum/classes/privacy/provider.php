<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for mod_forum.
 *
 * @package    mod_forum
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_forum\privacy;

use \core_privacy\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem implementation for mod_forum.
 *
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin has data.
    \core_privacy\metadata\provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\request\plugin_provider
{
    /**
     * @inheritdoc
     */
    public static function get_metadata(\core_privacy\metadata\item_collection $items)  {
    }

    /**
     * @inheritdoc
     */
    public static function get_contexts_for_userid(int $userid) : \core_privacy\request\resultset {

        list($ratingselect, $ratingjoin, $ratingparams, $ratinguserwhere) = \core_rating\privacy\provider::get_sql_join('rat', 'mod_forum', 'post', 'p.id', $userid);
        // Fetch all forum discussions, and forum posts.
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} mod ON mod.id = cm.module AND mod.name = 'forum'
            INNER JOIN {forum} f ON f.id = cm.instance
            INNER JOIN {forum_discussions} d ON d.forum = f.id
             LEFT JOIN {forum_posts} p ON p.discussion = d.id
             LEFT JOIN {forum_digests} dig ON dig.forum = f.id
             LEFT JOIN {forum_subscriptions} sub ON sub.forum = f.id
             LEFT JOIN {forum_track_prefs} pref ON pref.forumid = f.id
             LEFT JOIN {forum_read} hasread ON hasread.forumid = f.id
             LEFT JOIN {forum_discussion_subs} dsub ON dsub.forum = f.id
             {$ratingjoin}
                 WHERE (
                    p.userid        = :postuserid OR
                    d.userid        = :discussionuserid OR
                    dig.userid      = :digestuserid OR
                    sub.userid      = :subuserid OR
                    pref.userid     = :prefuserid OR
                    hasread.userid  = :hasreaduserid OR
                    dsub.userid     = :dsubuserid OR
                    {$ratinguserwhere}
                )
        ";
        // TODO add:
        // * Check uses of subsystems:
        // ** ratings (done)
        // ** tags?? (tag are on a postid and added by the author)
        // ** files (done)
        // ** comments
        // ** grades (should be covered by Course)

        $params = [
            'contextlevel'      => CONTEXT_MODULE,
            'postuserid'        => $userid,
            'discussionuserid'  => $userid,
            'digestuserid'      => $userid,
            'subuserid'         => $userid,
            'prefuserid'        => $userid,
            'hasreaduserid'     => $userid,
            'dsubuserid'        => $userid,
        ];
        $params += $ratingparams;

        $resultset = new \core_privacy\request\resultset();
        $resultset->add_from_sql($sql, $params);

        return $resultset;
    }

    protected static $writer = null;

    /**
     * @inheritdoc
     */
    public static function store_user_data(int $userid, array $contexts) {
        global $DB;

        if (empty($contexts)) {
            return;
        }

        $contextids = array_map(function($context) {
            return $context->id;
        }, $contexts);

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

        $sql = "SELECT
                    c.id AS contextid,
                    f.*,
                    cm.id AS cmid,
                    dig.maildigest,
                    sub.userid AS subscribed,
                    pref.userid AS tracked
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid
            INNER JOIN {forum} f ON f.id = cm.instance
             LEFT JOIN {forum_digests} dig ON dig.forum = f.id AND dig.userid = :digestuserid
             LEFT JOIN {forum_subscriptions} sub ON sub.forum = f.id AND sub.userid = :subuserid
             LEFT JOIN {forum_track_prefs} pref ON pref.forumid = f.id AND pref.userid = :prefuserid
                 WHERE (
                    c.id {$contextsql}
                )
        ";

        $params = [
            'digestuserid'  => $userid,
            'subuserid'     => $userid,
            'prefuserid'    => $userid,
        ];
        $params += $contextparams;

        // Keep a mapping of forumid to contextid.
        $mappings = [];

        $forums = $DB->get_recordset_sql($sql, $params);
        foreach ($forums as $forum) {
            // Store relevant metadata about this forum instance.
            static::store_digest_data($userid, $forum);
            static::store_subscription_data($userid, $forum);
            static::store_tracking_data($userid, $forum);

            $mappings[$forum->id] = $forum->contextid;
        }
        $forums->close();

        if (!empty($mappings)) {
            // Store all discussion data for this forum.
            static::store_discussion_data($userid, $mappings);

            // Store all post data for this forum.
            static::store_post_data($userid, $mappings);
        }
    }

    /**
     * Store all information about all discussions that we have detected this user to have access to.
     */
    protected static function store_discussion_data(int $userid, array $mappings) {
        global $DB;


        // Find all of the discussions, and discussion subscriptions for this forum.
        list($foruminsql, $forumparams) = $DB->get_in_or_equal(array_keys($mappings), SQL_PARAMS_NAMED);
        $sql = "SELECT
                    d.*,
                    dsub.preference
                  FROM {forum} f
            INNER JOIN {forum_discussions} d ON d.forum = f.id
             LEFT JOIN {forum_discussion_subs} dsub ON dsub.discussion = d.id
                 WHERE f.id ${foruminsql}
                   AND (
                        d.userid    = :discussionuserid OR
                        dsub.userid = :dsubuserid
                   )
        ";

        $params = [
            'discussionuserid'  => $userid,
            'dsubuserid'        => $userid,
        ];
        $params += $forumparams;

        $discussions = $DB->get_recordset_sql($sql, $params);

        foreach ($discussions as $discussion) {
            $context = \context::instance_by_id($mappings[$discussion->forum]);

            // Store related metadata for this discussion.
            static::store_discussion_subscription_data($userid, $context, $discussion);

            // Store the discussion content.
            writer::with_context($context)
                ->store_data(static::get_discussion_area($discussion), $discussion);

            // Forum discussions do not have any files associately directly with them.
        }

        $discussions->close();
    }

    /**
     * Store all information about all posts that we have detected this user to have access to.
     */
    protected static function store_post_data(int $userid, array $mappings) {
        global $DB;


        // Find all of the posts, and post subscriptions for this forum.
        list($foruminsql, $forumparams) = $DB->get_in_or_equal(array_keys($mappings), SQL_PARAMS_NAMED);
        list($ratingselect, $ratingjoin, $ratingparams, $ratinguserwhere) = \core_rating\privacy\provider::get_sql_join('rat', 'mod_forum', 'post', 'p.id', $userid);

        $sql = "SELECT
                    f.id AS forumid,
                    p.*,
                    d.name AS discussionname,
                    d.timemodified AS discussionmodified,
                    read.firstread,
                    read.lastread
                  FROM {forum} f
            INNER JOIN {forum_discussions} d ON d.forum = f.id
            INNER JOIN {forum_posts} p ON p.discussion = d.id
             LEFT JOIN {forum_read} read ON read.postid = p.id
            {$ratingjoin}
                 WHERE f.id ${foruminsql} AND
                (
                    p.userid = :postuserid OR
                    read.userid = :readuserid OR
                    {$ratinguserwhere}
                )
        ";

        $params = [
            'postuserid'    => $userid,
            'readuserid'    => $userid,
        ];
        $params += $forumparams;
        $params += $ratingparams;

        $posts = $DB->get_recordset_sql($sql, $params);

        foreach ($posts as $post) {
            $context = \context::instance_by_id($mappings[$post->forumid]);
            $postarea = static::get_post_area($post);

            // Store related metadata.
            static::store_read_data($userid, $context,  $post);

            // Store the post content.
            if ($post->userid == $userid) {
                $post->message = writer::with_context($context)->rewrite_pluginfile_urls($postarea, 'mod_forum', 'post', $post->id, $post->message);

                // Transform all user fields on the post.
                $post = \core_user\privacy\request\transformation::user($userid, $post, ['userid']);

                writer::with_context($context)
                    // Store the post.
                    ->store_data($postarea, $post)

                    // Store the associated files.
                    ->store_area_files($postarea, 'mod_forum', 'post', $post->id)
                    ;

                // Store all ratings against this post as the post belongs to the user. All ratings on it are ratings of their content.
                \core_rating\privacy\provider::store_area_ratings($userid, $context, $postarea, 'mod_forum', 'post', $post->id, false);
            }

            // Check for any ratings that the user has made on this post.
            \core_rating\privacy\provider::store_area_ratings($userid, $context, $postarea, 'mod_forum', 'post', $post->id, $userid, true);
        }
        $posts->close();
    }

    /**
     * Store data about daily digest preferences
     */
    protected static function store_digest_data(int $userid, \stdClass $forum) {
        if (null !== $forum->maildigest) {
            // The user has a specific maildigest preference for this forum.
            $a = (object) [
                'forum' => format_string($forum->name, true),
            ];

            switch ($forum->maildigest) {
            case 0:
                $a->type = new \lang_string('emaildigestoffshort', 'mod_forum');
                break;
            case 1:
                $a->type = new \lang_string('emaildigestcompleteshort', 'mod_forum');
                break;
            case 2:
                $a->type = new \lang_string('emaildigestsubjectsshort', 'mod_forum');
                break;
            }

            writer::with_context(\context_module::instance($forum->cmid))
                ->store_metadata([], 'digestpreference', $forum->maildigest, new \lang_string('privacy:digesttypepreference', 'mod_forum', $a));
        }
    }

    /**
     * Store data about whether the user subscribes to forum.
     */
    protected static function store_subscription_data(int $userid, \stdClass $forum) {
        if (null !== $forum->subscribed) {
            // The user is subscribed to this forum.
            writer::with_context(\context_module::instance($forum->cmid))
                ->store_metadata([], 'subscriptionpreference', 1, new \lang_string('privacy:subscribedtoforum', 'mod_forum'));
        }
    }

    /**
     * Store data about whether the user subscribes to this particular discussion.
     */
    protected static function store_discussion_subscription_data(int $userid, \context_module $context, \stdClass $discussion) {
        $area = static::get_discussion_area($discussion);
        if (null !== $discussion->preference) {
            // The user has a specific subscription preference for this discussion.
            $a = (object) [];

            switch ($discussion->preference) {
            case \mod_forum\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED:
                $a->preference = new \lang_string('unsubscribed', 'mod_forum');
                break;
            default:
                $a->preference = new \lang_string('subscribed', 'mod_forum');
                break;
            }

            writer::with_context($context)
                ->store_metadata(
                    $area,
                    'subscriptionpreference',
                    $discussion->preference,
                    new \lang_string('privacy:discussionsubscriptionpreference', 'mod_forum', $a)
                );
        }
    }

    /**
     * Store forum read-tracking data about a particular forum.
     *
     * This is whether a forum has read-tracking enabled or not.
     */
    protected static function store_tracking_data(int $userid, \stdClass $forum) {
        if (null !== $forum->tracked) {
            // The user has a main preference to track all forums, but has opted out of this one.
            writer::with_context(\context_module::instance($forum->cmid))
                ->store_metadata([], 'trackreadpreference', 1, new \lang_string('privacy:readtrackingdisabled', 'mod_forum'));
        }
    }

    /**
     * Store read-tracking information about a particular forum post.
     */
    protected static function store_read_data(int $userid, \context $context, \stdClass $post) {
        if (null !== $post->firstread) {
            $a = (object) [
                'firstread' => $post->firstread,
                'lastread'  => $post->lastread,
            ];

            static::$writer->store_metadata(
                static::get_post_area($post),
                'postread',
                (object) [
                    'firstread' => $post->firstread,
                    'lastread' => $post->lastread,
                ],
                new \lang_string('privacy:postwasread', 'mod_forum', $a)
            );
        }
    }

    /**
     * Get the discussion part of the subcontext.
     *
     * @param   object      $discussion
     * @return  array
     */
    protected static function get_discussion_area($discussion) : Array {
        $parts = [
            $discussion->timemodified,
            $discussion->name,
            $discussion->id,
        ];

        $discussionname = implode('-', $parts);

        return [
            get_string('discussions', 'mod_forum'),
            $discussionname,
        ];
    }

    /**
     * Get the post part of the subcontext.
     *
     * @param   object      $post
     * @return  array
     */
    protected static function get_post_area($post) : Array {
        $area = static::get_discussion_area((object) [
            'timemodified'  => $post->discussionmodified,
            'name'          => $post->discussionname,
            'id'            => $post->discussion,
        ]);

        $parts = [
            $post->created,
            $post->subject,
            $post->id,
        ];

        $area[] = implode('-', $parts);

        return $area;
    }
}

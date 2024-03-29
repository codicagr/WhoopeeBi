<?php


namespace App\Jobs\WhoopeeBi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncStoresJob
{
    public function __construct()
    {
        //
    }

    public function handle()
    {
        $maxLogActivityId = DB::connection('mysql_bi')->table('store_tags')->max('log_id');
        if(is_null($maxLogActivityId)) {
            $maxLogActivityId = 0;
        }

        $query = "SELECT stores.log_id, stores.created_at_log_activity, scroll_percentage, latest_scroll_at, session_cookie,
        stores.site_session_id, stores.visitor_id, stores.visitor_cookie,
        gender, dob, city, state, country, activity_type_id, weight, record_type,
        stores.item_pseudo_model, referrer_type, referrer_name, site_id, stores.store, shopping_categories.shopping_category
    FROM
     ((SELECT log_act.log_id, log_act.created_at_log_activity, scroll_percentage, latest_scroll_at, session_cookie,
             log_act.site_session_id, log_act.visitor_id, v.visitor_cookie,
             gender, dob, city, state, country, activity_type_id, act.weight, record_type, it.pseudo_model AS item_pseudo_model, referrer_type, referrer_name, log_act.site_id, t.name AS store
        FROM (SELECT id AS log_id, created_at AS created_at_log_activity, scroll_percentage, latest_scroll_at, session_cookie,
                     site_session_id, visitor_id, activity_type_id, record_type, record_id, site_id, first_http_referrer_id
              FROM goldenha_cdp.log_activities
              WHERE log_activities.url NOT LIKE '%house-of-style%' AND log_activities.id > " . $maxLogActivityId . "
              ) AS log_act
            JOIN (SELECT id, visitors.visitor_cookie, gender, dob, city, state, country
                 FROM goldenha_cdp.visitors
                 ) AS v ON log_act.visitor_id=v.id
            JOIN (SELECT name, log_activity_id
                  FROM goldenha_cdp.tags
                        JOIN goldenha_cdp.log_activity_tag ON log_activity_tag.tag_id = tags.id
                  WHERE tags.tag_category_id=1
                  ) AS t ON t.log_activity_id=log_act.log_id
            JOIN (SELECT id, site_item_id, pseudo_model
                  FROM goldenha_cdp.items
                  WHERE items.pseudo_model='Product' OR items.pseudo_model='Store'
                  ) AS it ON log_act.record_id = it.id AND log_act.record_type LIKE '%Item'
            JOIN (SELECT id, weight
                 FROM goldenha_cdp.activity_types
                 WHERE id!=6
                 ) AS act ON log_act.activity_type_id = act.id
            LEFT JOIN (SELECT id, referrer_type, referrer_name
                      FROM goldenha_cdp.http_referrers
                      ) AS http_ref ON log_act.first_http_referrer_id = http_ref.id)
        AS stores
            LEFT JOIN (SELECT log_act.id AS id, t.name AS shopping_category
                        FROM (SELECT id
                             FROM goldenha_cdp.log_activities
                             ) AS log_act
                             JOIN (SELECT tag.name, log_activity_tag.log_activity_id
                                  FROM (SELECT id, name
                                        FROM goldenha_cdp.tags
                                        WHERE tag_category_id=2
                                        ) AS tag
                                        JOIN goldenha_cdp.log_activity_tag ON log_activity_tag.tag_id = tag.id
                                  ) AS t ON t.log_activity_id=log_act.id
                        ) AS shopping_categories ON stores.log_id=shopping_categories.id
      );";

        $columns = [
            'log_id', 'created_at_log_activity', 'scroll_percentage', 'latest_scroll_at', 'session_cookie',
            'site_session_id', 'visitor_id', 'visitor_cookie',
            'gender', 'dob', 'city', 'state', 'country', 'activity_type_id', 'weight_activity',
            'record_type', 'item_pseudo_model', 'referrer_type', 'referrer_name',
            'site_id', 'store', 'shopping_category'
        ];

        DB::connection('mysql_bi')->table('store_tags')->insertUsing($columns, $query);

        Log::channel('jobs')->info('[Bi]: Sync Stores finished.');
    }
}

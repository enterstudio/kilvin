<?php

namespace Kilvin\Cp;

use DB;
use Cp;
use Site;
use Request;
use Carbon\Carbon;
use Kilvin\Core\Session;
use Kilvin\Core\Localize;

class Logging
{
    // --------------------------------------------------------------------

    /**
    * Clear the Control Panel Logs action
    *
    * @return string
    */
    public function clearCpLogs()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        DB::table('cp_log')->delete();

        Cp::log(__('admin.cleared_logs'));

        return $this->viewLogs();
    }

    // --------------------------------------------------------------------

    /**
    * View CP Logs
    *
    * @return string
    */
    public function viewLogs()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        // Number of results per page
         $perpage = 100;

        // Fetch the total number of logs for our paginate links
        $total = DB::table('cp_log')->count();

        if ( ! $rownum = Request::input('rownum')) {
            $rownum = 0;
        }

        // Run the query
        $sites_query = DB::table('sites')->select('site_id', 'site_name')->get();

        $sites = [];

        foreach($sites_query as $row) {
        	$sites[$row->site_id] = $row->site_name;
        }

        $query = DB::table('cp_log')
        	->orderBy('act_date', 'desc')
        	->limit($perpage)
        	->offset($rownum)
        	->get();

        // Build the output
        Cp::$title  = __('admin.view_log_files');
        Cp::$crumb  = Cp::anchor(BASE.'?C=Administration'.AMP.'area=utilities', __('admin.utilities')).
                       Cp::breadcrumbItem(__('admin.view_log_files'));

        $right_links[] = [BASE.'?C=Administration'.AMP.'M=utilities'.AMP.'P=clear_cplogs', __('admin.clear_logs')];

        $r  = Cp::header(__('admin.view_log_files'), $right_links);

        $r .= Cp::table('tableBorder', '0', '0', '100%');

        $r .= Cp::tableQuickRow('tableHeadingAlt',
                              array(
                                    __('cp.member_id'),
                                    __('admin.screen_name'),
                                    __('cp.ip_address'),
                                    __('cp.date'),
                                    __('cp.site'),
                                    __('cp.action')
                                   )
                             );

        $i = 0;

        foreach ($query as $row)
        {
            $r .= Cp::tableQuickRow('',
                                    array(
                                            $row->member_id,
                                            $row->screen_name,
                                            $row->ip_address,
                                            Localize::createHumanReadableDateTime($row->act_date),
                                            $sites[$row->site_id],
                                            nl2br($row->action)
                                          )
                                   );
        }

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv('littlePadding',
              Cp::quickDiv('crumblinks',
              Cp::pager(
                            BASE.'?C=Administration'.AMP.'M=utilities'.AMP.'P=view_logs',
                            $total,
                            $perpage,
                            $rownum,
                            'rownum'
                          )));


        Cp::$body   = $r;
    }


    // --------------------------------------------------------------------

    /**
    * View Throlling Log (if enabled)
    *
    * @return string
    */
    public function viewThrottleLog()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        if (Site::config('enable_throttling') == 'n') {

            $url = '<a href="?C=Administration&M=configManager&P=security_preferences">'.__('core.here').'</a>';

        	return Cp::errorMessage(
                sprintf(
                    __('admin.throttling_disabled'),
                    $url
                )
            );
        }

        $max_page_loads = 10;
		$time_interval	= 5;
		$lockout_time	= 30;

        if (is_numeric(Site::config('max_page_loads'))) {
			$max_page_loads = Site::config('max_page_loads');
		}

		if (is_numeric(Site::config('time_interval'))) {
			$time_interval = Site::config('time_interval');
		}

		if (is_numeric(Site::config('lockout_time'))) {
			$lockout_time = Site::config('lockout_time');
		}

        // Number of results per page
		$perpage = 100;

		if ( ! $rownum = Request::input('rownum')) {
            $rownum = 0;
        }

        // ------------------------------------
        //  Retrieve List of Devils
        // ------------------------------------

        $lockout = time() - $lockout_time;

        $offset = Carbon::now()->timestamp - time();

        $total = DB::table('throttle')
        	->where('hits', '>=', $max_page_loads)
        	->orWhere(function($q) use ($lockout)
        	{
        		$q->where('locked_out', 'y')->where('last_activity', '>', $lockout);
        	})
        	->count('ip_address');

		$query = DB::table('throttle')
			->select('ip_address', 'hits', 'locked_out', 'last_activity')
        	->where('hits', '>=', $max_page_loads)
        	->orWhere(function($q) use ($lockout)
        	{
        		$q->where('locked_out', 'y')->where('last_activity', '>', $lockout);
        	})
        	->orderBy('ip_address')
        	->offset($rownum)
        	->limit($perpage)
        	->get();

        // Build the output

		$r = Cp::tableOpen(array('class' => 'tableBorder', 'width' => '100%'));
		$r .= Cp::tableRow(array(
									array('text' => __('cp.ip_address'), 'class' => 'tableHeadingAlt', 'width' => '25%'),
									array('text' => __('admin.hits'), 'class' => 'tableHeadingAlt', 'width' => '20%'),
									//array('text' => __('admin.locked_out'), 'class' => 'tableHeadingAlt', 'width' => '15%'),
									array('text' => __('admin.last_activity'), 'class' => 'tableHeadingAlt', 'width' => '55%'),
									)
							);

		if ($query->count() == 0) {
			$r .= Cp::tableRow(array(array('text' => Cp::quickDiv('highlight', __('admin.no_throttle_logs')), 'colspan' => '5' )));
		}
		else
		{
			$i = 0;
			foreach($query as $row)
			{
				$r .= Cp::tableRow(array(
						array('text' => $row->ip_address),
						array('text' => $row->hits),
						//array('text' => $row->locked_out),
						array('text' => Localize::createHumanReadableDateTime($row->last_activity + $offset))
						)
				);
			} // End foreach
		}

		$r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv('littlePadding',
              Cp::quickDiv('crumblinks',
              Cp::pager(
                            BASE.'?C=Administration'.AMP.'M=utilities'.AMP.'P=viewThrottleLog',
                            $total,
                            $perpage,
                            $rownum,
                            'rownum'
                          )));

        Cp::$title = __('admin.view_throttle_log');
        Cp::$crumb = Cp::anchor(BASE.'?C=Administration'.AMP.'area=utilities', __('admin.utilities')).
			 		   Cp::breadcrumbItem(__('admin.view_throttle_log'));

        Cp::$body  = Cp::header(__('admin.view_throttle_log'));
        Cp::$body .= $r;
    }
}

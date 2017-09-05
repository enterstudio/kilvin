<?php

namespace Kilvin\Cp;

use Cp;
use DB;
use Site;
use Stats;
use Request;
use Carbon\Carbon;
use Kilvin\Core\Session;

class Utilities
{
    // --------------------------------------------------------------------

    /**
    * Delete Cache Form
    *
    * @return  string
    */
    public function clearCacheForm($message = false)
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        Cp::$title = __('admin.clear_caching');
        Cp::$crumb = Cp::anchor(BASE.'?C=Administration'.AMP.'area=utilities', __('admin.utilities')).
					  Cp::breadcrumbItem(__('admin.clear_caching'));

        Cp::$body = Cp::quickDiv('tableHeading', __('admin.clear_caching'));

        if ($message == true) {
            Cp::$body  .= Cp::quickDiv('success-message', __('admin.cache_deleted'));
        }

		Cp::$body .= Cp::div('box');
        Cp::$body .= Cp::formOpen(
        	[
        		'action' => 'C=Administration'.AMP.'M=utilities'.AMP.'P=clearCaching',
        	]
        );

        Cp::$body .= Cp::div('littleTopPadding');
        Cp::$body .= __('admin.clear_cache_details');
		Cp::$body .= '</div>'.PHP_EOL;

        Cp::$body .= Cp::quickDiv('paddingTop', Cp::input_submit(__('admin.clear_caching')));
        Cp::$body .= '</form>'.PHP_EOL;
        Cp::$body .= '</div>'.PHP_EOL;
    }

    // --------------------------------------------------------------------

    /**
    * Clear Caching for Site
    *
    * Right now it simply calls a method that empties the Laravel Cache
    *
    * @return  string
    */
    public function clearCaching()
    {
        cms_clear_caching();

        return $this->clearCacheForm(true);
    }

    // --------------------------------------------------------------------

    /**
    * Recount Statistics Form
    *
    * @return  string
    */
    public function recountStatistics()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        $sources = array('members', 'weblog_entries');

        Cp::$title = __('admin.recount_statistics');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=Administration'.AMP.'area=utilities', __('admin.utilities')).
            Cp::breadcrumbItem(__('admin.recount_statistics'));

		$right_links[] = [
			BASE.'?C=Administration'.AMP.'M=utilities'.AMP.'P=recountPreferences',
			__('admin.set_recount_prefs')
		];

		$r  = Cp::header(Cp::$title, $right_links);
        $r .= Cp::quickDiv('tableHeading', __('admin.recalculate'));
        $r .= Cp::quickDiv('box', __('admin.recount_info'));
        $r .= Cp::table('tableBorder', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::tableCell('tableHeadingAlt',
                                array(
                                        __('admin.source'),
                                        __('admin.records'),
                                        __('cp.action')
                                     )
                                ).
                '</tr>'.PHP_EOL;

        $i = 0;

        foreach ($sources as $val) {
			$source_count = DB::table($val)->count();

			$r .= '<tr>'.PHP_EOL;

			// Table name
			$r .= Cp::tableCell('', Cp::quickDiv('defaultBold', __('admin.stats_'.$val)), '20%');

			// Table rows
			$r .= Cp::tableCell('', $source_count, '20%');

			// Action
			$r .= Cp::tableCell(
                '',
                Cp::anchor(
                    BASE.'?C=Administration'.
                        AMP.'M=utilities'.
                        AMP.'P=performRecount'.
                        AMP.'TBL='.$val,
                    __('admin.do_recount')
                ),
                '20%'
            );
        }


		$r .= '<tr>'.PHP_EOL;

		// Table name
		$r .= Cp::tableCell('', Cp::quickDiv('defaultBold', __('admin.site_statistics')), '20%');

		// Table rows
		$r .= Cp::tableCell('', '4', '20%');

		// Action
		$r .= Cp::tableCell(
            '',
            Cp::anchor(
                BASE.'?C=Administration'.
                    AMP.'M=utilities'.
                    AMP.'P=performStatsRecount',
                __('admin.do_recount')
            ),
            '20%'
        );

        $r .= '</table>'.PHP_EOL;

        Cp::$body = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Recount Preferences Form (pretty much batch total)
    *
    * @return string
    */
    public function recountPreferences()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        $recount_batch_total = Site::config('recount_batch_total');

        Cp::$title = __('admin.utilities');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=Administration'.AMP.'area=utilities', __('admin.utilities')).
            Cp::breadcrumbItem(
                Cp::anchor(
                    BASE.'?C=Administration'.
                        AMP.'M=utilities'.
                        AMP.'P=recountStatistics',
                    __('admin.recount_statistics')
                )
            ).
            Cp::breadcrumbItem(__('admin.set_recount_prefs'));

        $r = Cp::quickDiv('tableHeading', __('admin.set_recount_prefs'));

        if (Request::input('U')) {
            $r .= Cp::quickDiv('success-message', __('admin.preference_updated'));
        }

        $r .= Cp::formOpen(
			['action' => 'C=Administration'.AMP.'M=utilities'.AMP.'P=updateRecountPreferences'],
			['return_location' => BASE.'?C=Administration'.AMP.'M=utilities'.AMP.'P=recountPreferences'.AMP.'U=1']
		);

        $r .= Cp::div('box');

        $r .= Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('admin.recount_instructions')));

        $r .= Cp::quickDiv('littlePadding', __('admin.recount_instructions_cont'));

        $r .= Cp::input_text('recount_batch_total', $recount_batch_total, '7', '5', 'input', '60px');

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('cp.update')));

        $r .= '</div>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        Cp::$body = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Save Recount Preferences
    *
    * @return string
    */
    public function updateRecountPreferences()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        $total = Request::input('recount_batch_total');

        if (empty($total) or ! is_numeric($total)) {
            return Utilities::recount_preferences_form();
        }

        DB::table('site_preferences')
            ->where('site_id', Site::config('site_id'))
            ->where('handle', 'recount_batch_total')
            ->update([
                    'value' => $total
                ]
            );

        return redirect('?C=Administration&M=utilities&P=recountPreferences&U=updated');
    }

    // --------------------------------------------------------------------

    /**
    * Recount Statistics
    *
    * @return string
    */
    public function performStatsRecount()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        $original_site_id = Site::config('site_id');

        $query = DB::table('sites')
        	->select('site_id')
        	->get();

        foreach($query as $row)
		{
			Site::setConfig('site_id', $row->site_id);

			Stats::update_member_stats();
			Stats::update_weblog_stats();
		}

		Site::setConfig('site_id', $original_site_id);

        Cp::$title = __('admin.utilities');
        Cp::$crumb = Cp::anchor(BASE.'?C=Administration'.AMP.'area=utilities', __('admin.utilities')).
            Cp::breadcrumbItem(Cp::anchor(BASE.'?C=Administration'.AMP.'M=utilities'.AMP.'P=recountStatistics', __('admin.recalculate'))).
            Cp::breadcrumbItem(__('admin.recounting'));

		Cp::$body  = Cp::quickDiv('tableHeading', __('admin.site_statistics'));
		Cp::$body .= Cp::div('success-message');
		Cp::$body .= __('admin.recount_completed');
		Cp::$body .= Cp::quickDiv('littlePadding', Cp::anchor(BASE.'?C=Administration'.AMP.'M=utilities'.AMP.'P=recountStatistics', __('admin.return_to_recount_overview')));
		Cp::$body .= '</div>'.PHP_EOL;
	}

    // --------------------------------------------------------------------

    /**
    * Weblog or Members recount
    *
    * @return string
    */
    public function performRecount()
    {
        if ( ! Session::access('can_admin_utilities')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $table = Request::input('TBL')) {
            return false;
        }

        $sources = ['members', 'weblog_entries'];

        if ( ! in_array($table, $sources)) {
            return false;
        }

   		if ( ! isset($_GET['T'])) {
        	$num_rows = false;
        }
        else
        {
        	$num_rows = $_GET['T'];
			settype($num_rows, 'integer');
        }

        $batch = Site::config('recount_batch_total');

		if ($table == 'members')
		{
			$total_rows = DB::table('members')->count();

			if ($num_rows !== false)
			{
				$query = DB::table('members')
					->select('member_id')
					->orderBy('member_id')
					->offset($num_rows)
					->limit($batch)
					->get();

				foreach ($query as $row)
				{
					$total_entries = DB::table('weblog_entries')
						->where('author_id', $row->member_id)
						->count();

					DB::table('members')
						->where('member_id', $row->member_id)
						->update(
						[
							'total_entries' => $total_entries
						]
					);
				}
			}
		}
		elseif ($table == 'weblog_entries')
		{
			$total_rows = DB::table('weblog_entries')->count();
		}

        Cp::$title = __('admin.utilities');
        Cp::$crumb = Cp::anchor(BASE.'?C=Administration'.AMP.'area=utilities', __('admin.utilities')).
			 		  Cp::breadcrumbItem(Cp::anchor(BASE.'?C=Administration'.AMP.'M=utilities'.AMP.'P=recountStatistics', __('admin.recalculate'))).
			 		  Cp::breadcrumbItem(__('admin.recounting'));


        $r = <<<EOT

	<script type="text/javascript">

        function standby()
        {
			if ($('#batchlink').css('display') == "block")
			{
				$('#batchlink').css('display', 'none');
				$('#wait').css('display', 'block');
        	}
        }

	</script>
EOT;

		$r .= PHP_EOL.PHP_EOL;

        $r .= Cp::quickDiv('tableHeading', __('admin.recalculate'));
        $r .= Cp::div('success-message');

		if ($num_rows === FALSE) {
			$total_done = 0;
		}
		else {
			$total_done = $num_rows + $batch;
		}

        if ($total_done >= $total_rows)
        {
            $r .= __('admin.recount_completed');
            $r .= Cp::quickDiv('littlePadding', Cp::anchor(BASE.'?C=Administration'.AMP.'M=utilities'.AMP.'P=recountStatistics', __('admin.return_to_recount_overview')));
        }
        else
        {
			$r .= Cp::quickDiv('littlePadding', __('admin.total_records').NBS.$total_rows);
			$r .= Cp::quickDiv('itemWrapper', __('admin.items_remaining').NBS.($total_rows - $total_done));

            $line = __('admin.click_to_recount');

        	$to = (($total_done + $batch) >= $total_rows) ? $total_rows : ($total_done + $batch);

            $line = str_replace("%x", $total_done, $line);
            $line = str_replace("%y", $to, $line);

            $link = "<a href='".
                BASE.'?C=Administration'.
                    AMP.'M=utilities'.
                    AMP.'P=performRecount'.
                    AMP.'TBL='.$table.
                    AMP.'T='.$total_done.
                "'  onclick='standby();'><b>".$line."</b></a>";

			$r .= '<div id="batchlink" style="display: block; padding:0; margin:0;">';
            $r .= Cp::quickDiv('littlePadding', BR.$link);
			$r .= '</div>'.PHP_EOL;


			$r .= '<div id="wait" style="display: none; padding:0; margin:0;">';
			$r .= BR.__('admin.standby_recount');
			$r .= '</div>'.PHP_EOL;

        }

		$r .= '</div>'.PHP_EOL;

        Cp::$body = $r;
   }

    // --------------------------------------------------------------------

    /**
    * PHP Info display!
    *
    * @return string
    */
    public function php_info()
    {
        phpinfo();
        exit;
    }
}

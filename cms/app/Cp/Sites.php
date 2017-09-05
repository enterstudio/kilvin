<?php

namespace Kilvin\Cp;

use Cp;
use DB;
use Site;
use Stats;
use Request;
use Cookie;
use Carbon\Carbon;
use Kilvin\Core\Session;

class Sites
{

    // --------------------------------------------------------------------

    /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
        if (Request::filled('M')) {
            if (method_exists($this, Request::input('M'))) {
                return $this->{Request::input('M')}();
            }
        }

        return $this->listSites();
    }

    // --------------------------------------------------------------------

    /**
    * Request Handler
    *
    * @return mixed
    */
    public function loadSite()
    {
        $domain_id = Request::input('domain_id');

        // -----------------------------------------
        //  No Domain? If site_id, we try to determine domain
        // -----------------------------------------

        if (empty($domain_id) && Request::input('site_id')) {

            $domain_id = $this->determineSiteDomainId();

            if (is_null($domain_id)) {
                session()->flash(
                    'cp-message',
                    __('sites.unable_to_load_site_no_domains')
                );

                return redirect('?C=Sites&M=listSites&msg=do-domains');
            }
        }

        // -----------------------------------------
        //  Insure we have a valid domain_id
        // -----------------------------------------

        if (empty($domain_id) OR ! is_numeric($domain_id)) {
            return redirect('?C=Sites&M=listSites');
        }

        $domain = DB::table('domains')
            ->where('domain_id', $domain_id)
            ->first();

        if (empty($domain)) {
            return redirect('?C=Sites&M=listSites');
        }

        // -----------------------------------------
        //  Are you authorized to view this site?
        // -----------------------------------------

        if (Session::userdata('group_id') != 1) {
            $assigned_sites = Session::userdata('assigned_sites');

            if (!isset($assigned_sites[$domain->site_id])) {
                return Cp::unauthorizedAccess();
            }
        }

        // -----------------------------------------
        //  Happy Day!!
        // -----------------------------------------

        Site::loadDomainPrefs($domain_id);

        Cookie::queue('cp_last_domain_id', $domain_id, 365*24*60);

        if (Request::input('location') == 'preferences') {
            return redirect('?C=Administration&area=site_preferences');
        }

        return redirect('');
    }

    // --------------------------------------------------------------------

    /**
    * Determine Site's Domain ID
    *
    * @return integer|null
    */
    private function determineSiteDomainId()
    {
        // Try to find it based on path
        $domain_id = DB::table('domains')
                ->where('site_id', Request::input('site_id'))
                ->where('cms_path', CMS_PATH)
                ->value('domain_id');

        // Find one where the path is empty
        // This will cause the site_config loader to automatically create paths
        if (empty($domain_id)) {
            $domain_id = DB::table('domains')
                ->where('site_id', Request::input('site_id'))
                ->where(function($q) {
                    $q->where('cms_path', '')->orWhereNull('cms_path');
                })
                ->value('domain_id');
        }

        // Right, then we will simply find the first domain for site
        if (empty($domain_id)) {
            $domain_id = DB::table('domains')
                ->where('site_id', Request::input('site_id'))
                ->value('domain_id');
        }

        // No domains set up for site! Doom!
        if (empty($domain_id)) {
            return null;
        }

        return $domain_id;
    }

    // --------------------------------------------------------------------

    /**
    * List Available Sites
    *
    * @return void
    */
    public function listSites()
    {
        if (sizeof(Session::userdata('assigned_sites')) == 0) {
            return Cp::unauthorizedAccess();
        }

        // -----------------------------------------
        //  Header
        // -----------------------------------------

        Cp::$title  = __('admin.site_management');
        Cp::$crumb  = __('admin.site_management');

        $right_links[] = [
            BASE.'?C=SitesAdministration'.AMP.'M=newSite',
            __('sites.create_new_site')
        ];

        $r = Cp::header(__('sites.choose_a_domain'), $right_links);

        // -----------------------------------------
        //  CP Message?
        // -----------------------------------------

        $cp_message = session()->pull('cp-message');

        if (!empty($cp_message)) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        // -----------------------------------------
        //  Choose a Site or Domain!
        // -----------------------------------------

        $r .= __('sites.choose_a_domain_details').'<br><br>';
        $r .= Cp::table('tableBorder', '0', '', '100%');

        $i = 0;

        $query = DB::table('sites')
            ->leftJoin('domains', 'sites.site_id', '=', 'domains.site_id')
            ->whereIn('sites.site_id', array_keys(Session::userdata('assigned_sites')))
            ->get();

        $domains = [];

        foreach($query as $row) {
            $domains[$row->site_name][] = $row;
        }

        foreach($domains as $site_name => $site_domains)
        {
            $r .= '<tr>'.PHP_EOL;

            $s = '<p>&nbsp;&nbsp;- <em>No domains for site.</em></p>';

            if (!empty($site_domains[0]->domain)) {
                $s = '';
                foreach($site_domains as $domain) {
                    $s .= '<p>&nbsp;&nbsp;- '.
                        Cp::anchor(
                            '/'.BASE."?C=Sites".
                                AMP.'M=loadSite'.
                                AMP."domain_id=".$domain->domain_id, $domain->domain).'</p>';
                }
            }

            $r .= Cp::tableCell('', '<strong>'.$site_name.'</strong>'.$s);

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv(
            'littlePadding',
            "<a href='/".BASE."?C=SitesAdministration".AMP."M=listSites'><em>&#187;&nbsp;<strong>".__('cp.edit_sites')."</strong></em></a>");

        Cp::$body = $r;
    }
}

<{include file='db:wggithub_header.tpl' }>

<div class="wgg-wrap">

    <{if !empty($error)}>
    <div class="wgg-error"><strong><{$error}></strong></div>
    <{/if}>

    <!-- show directories in tabs -->
    <{if $directoriesCount|default:0 > 0}>
    <!-- filter area -->
    <div id="wgg-filter-bar" class="wgg-filter">
        <span><{$smarty.const._MA_WGGITHUB_FILTER_SORTBY}>:</span>
        <div class="wgg-btn-group" role="group" aria-label="Filter Sortby">
            <button id="sortbyname" onclick="wggExecuteClick('index.php?op=list&fsortby=name&frelease=<{$frelease}>')" type="button" class="wgg-btn <{if $menu == 0 || $fsortby =='name'}>is-disabled<{/if}>"<{if $menu == 0 || $fsortby =='name'}> disabled="disabled"<{/if}>><{$smarty.const._MA_WGGITHUB_FILTER_SORTBY_NAME}></button>
            <button id="sortbyupdate" onclick="wggExecuteClick('index.php?op=list&fsortby=update&frelease=<{$frelease}>')" type="button" class="wgg-btn <{if $menu == 0 || $fsortby =='update'}>is-disabled<{/if}>"<{if $menu == 0 || $fsortby =='update'}> disabled="disabled"<{/if}>><{$smarty.const._MA_WGGITHUB_FILTER_SORTBY_UPDATE}></button>
        </div>
        <span id="filter_release_label"><{$smarty.const._MA_WGGITHUB_FILTER_RELEASE}>:</span>
        <div id="filter_release_bar" class="wgg-btn-group" role="group" aria-label="Filter Releases">
            <button id="relfinal" onclick="wggExecuteClick('index.php?op=list&frelease=final&fsortby=<{$fsortby}>')" type="button" class="wgg-btn <{if $menu == 0 || $frelease =='final'}>is-disabled<{/if}>"<{if $menu == 0 || $frelease =='final'}> disabled="disabled"<{/if}>><{$smarty.const._MA_WGGITHUB_FILTER_RELEASE_FINAL}></button>
            <button id="relany" onclick="wggExecuteClick('index.php?op=list&frelease=any&fsortby=<{$fsortby}>')" type="button" class="wgg-btn <{if $menu == 0 || $frelease =='any'}>is-disabled<{/if}>"<{if $menu == 0 || $frelease =='any'}> disabled="disabled"<{/if}>><{$smarty.const._MA_WGGITHUB_FILTER_RELEASE_ANY}></button>
            <{if $showBtnAll|default:false}>
        <button id="relall" onclick="wggExecuteClick('index.php?op=list&frelease=all&fsortby=<{$fsortby}>')" type="button" class="wgg-btn <{if $menu == 0 || $frelease =='all'}>is-disabled<{/if}>"<{if $menu == 0 || $frelease =='all'}> disabled="disabled"<{/if}>><{$smarty.const._MA_WGGITHUB_FILTER_RELEASE_ALL}></button>
            <{/if}>
        </div>
    </div>

<!-- main horizontal tabs -->
    <div class="wgg-tabs wgg-tabs--horizontal" data-wgg-tabs>
        <ul class="wgg-tablist" role="tablist">
            <li class="<{if isset($menu) && $menu == 0}>is-active<{/if}>" role="presentation">
                <a href="#wgg-home" role="tab" data-wgg-target="wgg-home" data-wgg-filters="0" data-wgg-filterrelease="0"><{$smarty.const._MA_WGGITHUB_INDEX}></a>
            </li>
            <{foreach item=directory from=$directories}>
            <li class="<{if isset($menu) && $menu == $directory.id}>is-active<{/if}>" role="presentation">
                <a href="#wgg-menu<{$directory.id}>" role="tab" data-wgg-target="wgg-menu<{$directory.id}>" data-wgg-filters="1" data-wgg-filterrelease="<{$directory.dir_filterrelease}>"><{$directory.name}></a>
            </li>
            <{/foreach}>
        </ul>

        <div class="wgg-panes">
            <div id="wgg-home" data-wgg-menu="0" role="tabpanel" class="wgg-pane wgg-mainpane <{if $menu == 0}>is-active<{/if}>">
                <p class="wgg-center"><img class="wgg-logo" src="assets/images/logoModule.png" alt="<{$smarty.const._MA_WGGITHUB_TITLE}>" title="<{$smarty.const._MA_WGGITHUB_TITLE}>"></p>
                <h3><{$smarty.const._MA_WGGITHUB_DESC}></h3>
                <p><{$smarty.const._MA_WGGITHUB_INDEX_DESC}></p>
                <p class="wgg-lastupdate"><{$smarty.const._MA_WGGITHUB_INDEX_LASTUPDATE}>: <{$lastUpdate|default:'-'}> GMT</p>
                <{if $apiexceed|default:''}>
                <p><{$smarty.const._MA_WGGITHUB_READGH_ERROR_APILIMIT}></p>
                <{elseif $apierror|default:''}>
                <p><{$smarty.const._MA_WGGITHUB_READGH_ERROR_APIOTHER}></p>
                <{else}>
                <p><{$smarty.const._MA_WGGITHUB_INDEX_UPTODATE}></p>
                <{/if}>
            </div>

            <{foreach item=directory from=$directories}>
            <div id="wgg-menu<{$directory.id}>" data-wgg-menu="<{$directory.id}>" role="tabpanel" class="wgg-pane wgg-mainpane <{if $menu == $directory.id}>is-active<{/if}>">
                <div class="wgg-dirinfo">
                    <h4>
                        <{$directory.countRepos}>
                        <{if $permGlobalRead && ($directory.dir_autoupdate == 0)}>
                        <a id="btn_update" class="wgg-btn wgg-btn--small wgg-btn--right" href="index.php?op=update_dir&amp;dir_name=<{$directory.name}>"><{$smarty.const._MA_WGGITHUB_DIRECTORY_UPDATE}></a>
                        <{/if}>
                    </h4>
                    <p><{$directory.descr}></p>
                </div>

                <!-- vertical tabs: repositories of this directory -->
                <div class="wgg-tabs wgg-tabs--vertical" data-wgg-tabs>
                    <ul class="wgg-tablist" role="tablist" aria-orientation="vertical">
                        <{if $directory.previousRepos|default:''}>
                        <li class="wgg-more"><a id="btn_previous" href="index.php?op=list<{$directory.previousOp}>&amp;menu=<{$directory.id}>"> ... </a></li>
                        <{/if}>
                        <{foreach name=repo item=repo from=$directory.repos}>
                        <li class="<{if $smarty.foreach.repo.first}>is-active<{/if}>" role="presentation">
                            <a href="#wgg-detail<{$repo.id}>" role="tab" data-wgg-target="wgg-detail<{$repo.id}>"><{$repo.name}></a>
                        </li>
                        <{/foreach}>
                        <{if $directory.nextRepos|default:''}>
                        <li class="wgg-more"><a id="btn_next" href="index.php?op=list<{$directory.nextOp}>&amp;menu=<{$directory.id}>"> ... </a></li>
                        <{/if}>
                    </ul>

                    <div class="wgg-panes">
                        <{foreach name=repo item=repo from=$directory.repos}>
                        <div id="wgg-detail<{$repo.id}>" role="tabpanel" class="wgg-pane<{if $smarty.foreach.repo.first}> is-active<{/if}>">
                            <h3 class="wgg-headline"><{$smarty.const._MA_WGGITHUB_REPOSITORY}>: <{$repo.name}></h3>
                            <div class="wgg-row">
                                <div class="wgg-cell wgg-headline">
                                    <p><span aria-hidden="true">&#128197;</span> <{$smarty.const._MA_WGGITHUB_REPOSITORY_CREATEDAT}>: <{$repo.createdat}></p>
                                </div>
                                <div class="wgg-cell wgg-headline">
                                    <p><span aria-hidden="true">&#128197;</span> <{$smarty.const._MA_WGGITHUB_REPOSITORY_UPDATEDAT}>: <{$repo.updatedat}></p>
                                </div>
                            </div>
                            <{if $repo.releases|default:''}>
                            <div class="wgg-headline">
                                <p><{$smarty.const._MA_WGGITHUB_RELEASES}>:</p>
                                <{foreach item=release from=$repo.releases}>
                                <p>
                                    <{$release.name}> <span class="wgg-release-meta">(<{$release.publishedat}>)</span>
                                    <a class="wgg-btn wgg-btn--small wgg-btn--info" href="<{$release.zipballurl}>" title="<{$smarty.const._MA_WGGITHUB_RELEASE_ZIP}>"><{$smarty.const._MA_WGGITHUB_RELEASE_ZIP}></a>
                                    <a class="wgg-btn wgg-btn--small wgg-btn--info" href="<{$release.tarballurl}>" title="<{$smarty.const._MA_WGGITHUB_RELEASE_TAR}>"><{$smarty.const._MA_WGGITHUB_RELEASE_TAR}></a>
                                </p>
                                <{/foreach}>
                            </div>
                            <{/if}>
                            <div class="wgg-headline">
                                <a class="wgg-btn" href="<{$repo.htmlurl}>" title="<{$smarty.const._MA_WGGITHUB_REPOSITORY_GOTO}>" target="_blank" rel="noopener"><{$smarty.const._MA_WGGITHUB_REPOSITORY_GOTO}></a>
                            </div>
                            <{if $repo.readme.gitbook_link|default:''}>
                            <div class="wgg-headline">
                                <a class="wgg-btn wgg-btn--warn" href="<{$repo.readme.gitbook_link}>" title="<{$smarty.const._MA_WGGITHUB_GITBOOK_GOTO}>" target="_blank" rel="noopener"><{$smarty.const._MA_WGGITHUB_GITBOOK_GOTO}></a>
                            </div>
                            <{/if}>
                            <div class="wgg-content">
                                <{if $permReadmeUpdate|default:''}>
                                <a class="wgg-btn wgg-btn--small wgg-btn--right" href="index.php?op=update_readme&amp;repo_id=<{$repo.id}>&amp;repo_user=<{$repo.user}>&amp;repo_name=<{$repo.name}>" title="<{$smarty.const._MA_WGGITHUB_README_UPDATE}>"><{$smarty.const._MA_WGGITHUB_README_UPDATE}></a>
                                <{/if}>
                                <{$repo.readme.content_clean|default:''}>
                            </div>
                        </div>
                        <{/foreach}>
                    </div>
                </div>
            </div>
            <{/foreach}>
        </div>
    </div>
    <{/if}>
    <!-- end show directories in tabs -->

    <!-- show single repo -->
    <{if $showSingle|default:false}>
        <div id="wgg-detail<{$repo.id}>">
            <h3 class="wgg-headline"><{$smarty.const._MA_WGGITHUB_REPOSITORY}>: <{$repo.name}></h3>
            <div class="wgg-row">
                <div class="wgg-cell wgg-headline">
                    <p><span aria-hidden="true">&#128197;</span> <{$smarty.const._MA_WGGITHUB_REPOSITORY_CREATEDAT}>: <{$repo.createdat}></p>
                </div>
                <div class="wgg-cell wgg-headline">
                    <p><span aria-hidden="true">&#128197;</span> <{$smarty.const._MA_WGGITHUB_REPOSITORY_UPDATEDAT}>: <{$repo.updatedat}></p>
                </div>
            </div>
            <{if $repo.releases|default:''}>
                <div class="wgg-headline">
                    <p><{$smarty.const._MA_WGGITHUB_RELEASES}>:</p>
                    <{foreach item=release from=$repo.releases}>
                        <p>
                            <{$release.name}> <span class="wgg-release-meta">(<{$release.publishedat}>)</span>
                            <a class="wgg-btn wgg-btn--small wgg-btn--info" href="<{$release.zipballurl}>" title="<{$smarty.const._MA_WGGITHUB_RELEASE_ZIP}>"><{$smarty.const._MA_WGGITHUB_RELEASE_ZIP}></a>
                            <a class="wgg-btn wgg-btn--small wgg-btn--info" href="<{$release.tarballurl}>" title="<{$smarty.const._MA_WGGITHUB_RELEASE_TAR}>"><{$smarty.const._MA_WGGITHUB_RELEASE_TAR}></a>
                        </p>
                    <{/foreach}>
                </div>
            <{/if}>
            <div class="wgg-headline">
                <a class="wgg-btn" href="<{$repo.htmlurl}>" title="<{$smarty.const._MA_WGGITHUB_REPOSITORY_GOTO}>" target="_blank" rel="noopener"><{$smarty.const._MA_WGGITHUB_REPOSITORY_GOTO}></a>
            </div>
            <{if $repo.readme_val.gitbook_link|default:''}>
                <div class="wgg-headline">
                    <a class="wgg-btn wgg-btn--warn" href="<{$repo.readme_val.gitbook_link}>" title="<{$smarty.const._MA_WGGITHUB_GITBOOK_GOTO}>" target="_blank" rel="noopener"><{$smarty.const._MA_WGGITHUB_GITBOOK_GOTO}></a>
                </div>
            <{/if}>
            <div class="wgg-content">
                <{$repo.readme_val.content_clean|default:''}>
            </div>
        </div>
    <{/if}>
    <!-- end show single repo -->
</div><!-- /.wgg-wrap -->

<script type="text/javascript">
    (function () {
        'use strict';

        function byId(id) { return document.getElementById(id); }
        function setDisabled(id, disabled) {
            var el = byId(id);
            if (!el) { return; }
            el.classList.toggle('is-disabled', disabled);
            el.disabled = disabled; /* native state: blocks keyboard focus and activation too */
        }

        /* reload page with chosen filter, keeping the currently open main tab (numeric directory id, 0 = home) */
        window.wggExecuteClick = function (href) {
            var pane = document.querySelector('.wgg-mainpane.is-active');
            var menu = pane ? pane.getAttribute('data-wgg-menu') : '0';
            window.location.href = href + '&menu=' + encodeURIComponent(menu);
        };

        window.wggToggleFilterRelease = function (display) {
            setDisabled('relfinal', true);
            setDisabled('relany', true);
            setDisabled('relall', true);

            if (1 === Number(display)) {
                <{if $frelease == 'final'}>
                    setDisabled('relany', false);
                    <{if $showBtnAll|default:false}>setDisabled('relall', false);<{/if}>
                        <{/if}>
                            <{if $frelease == 'any'}>
                                setDisabled('relfinal', false);
                                <{if $showBtnAll|default:false}>setDisabled('relall', false);<{/if}>
                                    <{/if}>
                                        <{if $frelease == 'all'}>
                                            setDisabled('relfinal', false);
                                            setDisabled('relany', false);
                                            <{/if}>
                                                }
                                                };

                                                window.wggToggleFilters = function (display) {
                                                setDisabled('sortbyname', true);
                                                setDisabled('sortbyupdate', true);
                                                if (1 === Number(display)) {
                                                <{if $fsortby == 'name'}>
                                                setDisabled('sortbyupdate', false);
                                                <{else}>
                                                setDisabled('sortbyname', false);
                                                <{/if}>
                                            }
                                                };

                                                /* generic tab handling: works for horizontal and vertical groups, also nested */
                                                function initTabs(group) {
                                                var list  = group.querySelector(':scope > .wgg-tablist');
                                                var panes = group.querySelector(':scope > .wgg-panes');
                                                if (!list || !panes) { return; }

                                                function activate(link) {
                                                var targetId = link.getAttribute('data-wgg-target');
                                                var items = list.querySelectorAll(':scope > li');
                                                for (var i = 0; i < items.length; i++) { items[i].classList.remove('is-active'); }
                                                link.parentNode.classList.add('is-active');

                                                for (var m = 0; m < links.length; m++) {
                                                links[m].setAttribute('aria-selected', links[m] === link ? 'true' : 'false');
                                            }

                                                var children = panes.children;
                                                for (var j = 0; j < children.length; j++) {
                                                if (children[j].classList.contains('wgg-pane')) {
                                                children[j].classList.toggle('is-active', children[j].id === targetId);
                                            }
                                            }
                                                if (link.hasAttribute('data-wgg-filters')) {
                                                window.wggToggleFilters(link.getAttribute('data-wgg-filters'));
                                                window.wggToggleFilterRelease(link.getAttribute('data-wgg-filterrelease'));
                                            }
                                            }

                                                var links = list.querySelectorAll(':scope > li > a[data-wgg-target]');
                                                for (var k = 0; k < links.length; k++) {
                                                links[k].addEventListener('click', function (e) {
                                                e.preventDefault();
                                                activate(this);
                                            });
                                            }

                                                /* initial state: make sure one tab is active and always apply its filter state,
                                                   even if the tab was already selected by the server (paging link or direct URL) */
                                                var current = list.querySelector(':scope > li.is-active > a[data-wgg-target]');
                                                if (current) {
                                                activate(current);
                                            } else if (links.length) {
                                                activate(links[0]);
                                            }
                                            }

                                                var groups = document.querySelectorAll('[data-wgg-tabs]');
                                                for (var g = 0; g < groups.length; g++) { initTabs(groups[g]); }
                                                })();
</script>

<{include file='db:wggithub_footer.tpl' }>

{if $error}
<div class="alert alert-danger text-center">
    {$error}
</div>
{/if}
{if $info}
<div class="alert alert-info textcenter">
    {$info}
</div>
{/if}
{if $external}
<br /><br />
<div class="textcenter">
    {$code}
</div>
<br /><br /><br /><br />
{else}

{if $manageHost}
    {if $records|@count gt 0}
        <h2>Manage Child Host [{$hostname}]</h2>
        <p>View and manage the IP addresses assigned to the Child Host. At least one IP address needs to be assigned to the Child Host.
        <div class="table-responsive">
            <table class="table table-hover table-condensed table-striped">
                <thread>
                    <tr>
                        <th>Hostname</th>
                        <th>IP Address</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thread>
                <tbody>
                    {foreach $records as $key => $ipArray}
                        {if $key eq $hostname}
                            {assign var="count" value=$ipArray|@count}
                            {foreach $ipArray as $ipRecord}
                                <tr>
                                    <td><div class="sw-ellipis">{$key}</div></td>
                                    <td><div class="sw-ellipis">{$ipRecord}</div></td>
                                    <td class="text-right">
                                        <form class="form-horizontal" role="form" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=manageChildHosts">
                                            <input type="hidden" name="ipHost" value="{$key}"/>
                                            <input type="hidden" name="ipRecord" value="{$ipRecord}"/>
                                            {if $count gt 1} 
                                                <button onclick="return formConfirm();" type="submit" name="sub" value="Delete Host IP" class="btn btn-danger">Delete Host IP</button>
                                            {else}
                                                <button onclick="return formConfirm();" type="submit" name="sub" value="Delete Host IP" class="btn btn-danger disabled" disabled>Delete Host IP</button>
                                            {/if}
                                        </form>
                                    </td>
                                </tr>
                            {/foreach}
                        {/if}
                    {/foreach}
                </tbody>
            </table>
        </div>
    {/if}
    <h2>Add New IP Address</h2>
    <p>Add a new IP address to the Child Host record. Both IPv4 and IPv6 records are supported.</p></br>
    <form class="form-horizontal" id="form" role="form" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=manageChildHosts">
    <input type="hidden" name="ipHost" value="{$hostname}"/>
        <div class="form-group">
            <label class="control-label col-sm-2" for="newIPAddress">IP Address:</label>
            <div class="col-xs-5">
                <input name="ipRecord" type="text" class="form-control" id="newIPAddress" placeholder="Enter IPv4 or IPv6 address">
            </div>
        </div>
        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
                <button type="submit" name="sub" value="Save Host IP" class="btn btn-primary">Add New IP Address</button>
            </div>
        </div>
    </form>
{else}

{if $records|@count gt 0}
    <h2>Manage Child Host Records</h2>
    <p>Manage the current Child Host Records assigned to this domain name.
    <div class="table-responsive">
        <table class="table table-hover table-condensed table-striped">
            <thread>
                <tr>
                    <th>Hostname</th>
                    <th class="text-right">Action</th>
                </tr>
            </thread>
            <tbody>
                {foreach $records as $key => $ipArray}
                    <tr>
                        <td><div class="sw-ellipis">{$key}</div></td>
                        <td class="text-right">
                            <form class="form-horizontal" role="form" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=manageChildHosts">
                                <input type="hidden" name="ipHost" value="{$key}"/>
                                <button type="submit" name="sub" value="Manage Host" class="btn btn-primary">Manage Host</button>
                                <button onclick="return formConfirm();" type="submit" name="sub" value="Delete Host" class="btn btn-danger">Delete Host</button>
                            </form>
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{/if}
<h2>Add New Child Host Record</h2>
<p>Use the options below to create new child host records for this domain name.</p>
<div class="alert alert-info textcenter">
    IMPORTANT: When adding new child host records please only enter the hostname you wish to create. For 'ns1.yourdomain.com' you would only need to enter 'ns1'.
</div>
<form class="form-horizontal" id="form" role="form" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=manageChildHosts">
    <div class="form-group">
        <label class="control-label col-sm-3" for="newHostName">Child Hostname:</label>
        <div class="col-xs-5">
            <input name="newHostName" type="text" class="form-control" id="newHostName" placeholder="Enter hostname (without domain portion)">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-sm-3" for="newIPAddress">IP Address:</label>
        <div class="col-xs-5">
            <input name="ipRecord" type="text" class="form-control" id="newIPAddress" placeholder="Enter IPv4 or IPv6 address">
        </div>
    </div>
    <div class="form-group">
        <div class="col-sm-offset-3 col-sm-10">
            <button type="submit" name="sub" value="Save Host" class="btn btn-primary">Add New Host</button>
        </div>
    </div>
</form>
{/if}
{/if}
{if $manageHost}
<form method="post" action="{$smarty.server.REQUEST_URI}">
    <input type="hidden" name="id" value="{$domainid}" />
    <p><input type="submit" value="{$LANG.clientareabacklink}" class="btn" /></p>
</form>
{/if}
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

    {* Only display the DNS management section if the TLD can have DNS managed on it or the domain itself has the addon enabled already *}
    {if ($tlddnsmanagement == 1 or $tldemailforwarding == 1) or ($dnsmanagement == 1 or $emailforwarding == 1)}
        {if $dnsmanagement == 0 and $emailforwarding == 0}
            <h2>Set DNS Type</h2>
            <div class="alert alert-info textcenter">
            <strong>NOTICE:</strong> If you wish to utilise DNS Hosting, create email forwarders and use the URL forwarding options please visit the <a href="clientarea.php?action=domaindetails&id={$domainid}#tabAddons">addons menu</a> to activate the required addon first.
            </div>
        {else}
            <h2>Set DNS Type</h2>
            <p>Set and manage your DNS configuration. You may choose from 'Parked', 'URL & Email Forwarding + DNS Hosting' or 'DNS Hosting'.</p>
            <br/>
            <form class="form-inline" id="form" role="form" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=domainOptions">
                <input type="hidden" name="sub" value="save" />
                <input type="hidden" name="opt" value="dnstype"/>
                <div class="form-group">
                    <label for="option">Your domain DNS configuration is currently set to use:</label>
                    <span>&nbsp;</span>
                    <select type="text" name="option" class="form-control" id="option" onchange="formSubmitDNS();">
                        <optgroup label="Domain Options"></optgroup>
                        <option {if $dnsConfigType == 3}selected{/if} value="3">Parked</option>
                        {* Only display these options if it's enabled on the domain or the TLD settings OR the DNS config is already set to it *}
                        {if ($tldemailforwarding == 1 or $emailforwarding == 1) or $dnsConfigType == 2}
                            <option {if $dnsConfigType == 2}selected{/if} value="2">URL & Email Forwarding + DNS Hosting</option>
                        {/if}
                        {if ($tlddnsmanagement == 1 or $dnsmanagement == 1) or $dnsConfigType == 4}
                            <option {if $dnsConfigType == 4}selected{/if} value="4">DNS Hosting</option>
                        {/if}
                    </select>
                </div>
            </form>
            <br/>
            <div class="alert alert-info textcenter">
                <strong>WARNING: </strong>When an alternate DNS configuration type is selected any existing DNS records, including email or URL forwarding settings, will be deleted and the DNS zone will be reset. Unnecessary changes may have an undesirable outcome causing your website and emails to go offline.
            </div>
        {/if}
    {/if}

    {if $tld eq "xxx"}
        <h2>Update .XXX Membership Details</h2>
        <p>In order to have your .XXX domain name resolve on the internet you will need to complete ICM Registry's <a href="http://icmregistry.com/members/">free membership appliation</a> to become a member of the Sponsored Community. Once your membership ID has been issued by ICM Registry you can assign it to the domain name using the options below.</p>
        <br/>
        <form class="form-horizontal" id="form" role="form" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=domainOptions">
            <input type="hidden" name="sub" value="save" />
            <input type="hidden" name="opt" value="xxxmembership"/>
            <div class="form-group">
                <label class="control-label col-sm-3" for="xxxToken">XXX Membership Token:</label>
                <div class="col-xs-5">
                    <input name="xxxToken" type="text" class="form-control" id="xxxToken" placeholder="Enter XXX Membership Token" required>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-10">
                    <button type="submit" class="btn btn-primary">Update XXX Membership</button>
                </div>
            </div>
        </form>
        <br/>
    {/if}

    {if $icannStatus ne "N/A" and $icannStatus ne "Verified"}
        <h2>Resend WHOIS Verification Email</h2>
        <p>If you need to resend the ICANN WHOIS Verification email please use the options below. Failure to complete verification on your WHOIS contact information may result in your domain name being suspended.</p>
        <br/>
        <form class="form-horizontal" id="form" role="form" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=domainOptions">
            <input type="hidden" name="sub" value="save" />
            <input type="hidden" name="opt" value="resendwhoisverif"/>
            <center>
                <input type="submit" value="Resend Whois Verification Email" class="btn btn-lg btn-success" />
            </center>
        </form>
    {/if}
{/if}
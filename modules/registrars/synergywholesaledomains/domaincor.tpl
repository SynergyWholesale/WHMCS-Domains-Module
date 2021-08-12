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
    <h2>Initiate Change of Registrant</h2>
    <p>A Change of Registrant for .AU domain names is the process of updating the eligibility information tied to the domain. This will begin the initial process of sending an email to the registrant email address. Details contained in the email will outline the steps required to complete the Change of Registrant process.</p><br />

    <div class="alert alert-info textcenter">
        <p>A Change of Registrant forces the domains renewal period to be reset.</p>
    </div>

    <form class="form-horizontal" id="form" role="form" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=initiateAuCorClient">
        <input type="hidden" name="sub" value="save" />
        <div class="form-group">
            <label class="control-label col-sm-4" for="keyTag">Renewal length:</label>
            <div class="col-xs-5">
                <input name="keyTag" type="text" class="form-control" id="keyTag" placeholder="Enter Key Tag">
            </div>
        </div>
        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
                <button type="submit" class="btn btn-primary">Create Invoice</button>
            </div>
        </div>
    </form>
{/if}

{if $error}
    <div class="alert alert-danger text-center">
        {$error}
    </div>
{/if}
{if $info}
    <div class="alert alert-info text-center">
        {$info}
    </div>
{/if}
{if $external}
    <br /><br />
    <div class="text-center">
        {$code}
    </div>
    <br /><br /><br /><br />
{else}
    <h2>Initiate Change of Registrant</h2>
    <p>A Change of Registrant for .AU domain names is the process of updating the eligibility information tied to the domain. This will begin the initial process of sending an email to the registrant email address. Details contained in the email will outline the steps required to complete the Change of Registrant process.</p><br />

    <div class="alert alert-info text-center">
        <p>A Change of Registrant forces the domains renewal period to be reset.</p>
    </div>

    {if $cor}
        <div class="alert alert-danger text-center">
            <p>A Change of Registrant invoice already exists for this domain. Invoice: <a href="viewinvoice.php?id={$cor}">{$cor}</a></p>
        </div>
    {/if}

    {if $pending_cor}
        <div class="alert alert-danger text-center">
            <p>A Change of Registrant already exists for this domain, Please check your registrant email account.</a></p>
        </div>
    {/if}

    {if empty($pending_cor) && empty($cor)}
        <form class="form-horizontal" id="form" role="form" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=initiateAuCorClient">
            <div class="form-group">
                <label class="control-label col-sm-4" for="renewalLength">Renewal length:</label>
                <div class="col-xs-5">
                    <select name="renewalLength" class="form-control" id="renewalLength" form="form">
                        <optgroup label="Renewal Length"></optgroup>
                        {foreach $pricing as $key => $value}
                            {if $key eq "1"}<option value="{$key}">{$key} Year - {$value['renew']}</option>{/if}
                            {if $key gt "1"}<option value="{$key}">{$key} Years - {$value['renew']}</option>{/if}
                        {/foreach}
                    </select>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button onclick="Toast('success', 'toast-top-right', 'Successfully created COR, Please check your invoices');" type="submit" class="btn btn-primary">Create Invoice</button>
                </div>
            </div>
        </form>
    {/if}
{/if}

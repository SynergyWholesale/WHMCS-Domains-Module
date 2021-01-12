<script type="text/javascript">
    $(document).ready(EmailForwardPageReady({$domainid}));
</script>

<h3>Email forwarding</h3>
<p>Use the options below to create, manage and delete Email forwarding options set on the domain name.</p>

<input id="domainid" type="hidden" name="domainid" value="{$domainid}" />
<input id="domainname" type="hidden" name="domainname" value="{$domain}" />

<div class="row sw-no-margin justify-content-between">
    <h3 class="sw-inline">Email Forwards</h3>
    <button type="button" class="btn btn-success sw-insert-row pull-right"><span class="fas fa-plus"></span></button>
</div>
<div class="container col-lg-12 sw-row-table" id="emailforwards" style="font-size: 14px;">
    <div class="row" id="sw-heading">
        <div class="col-lg-4">Prefix</div>
        <div class="col-lg-2"></div>
        <div class="col-lg-4">Forward To</div>
        <div class="col-lg-2 "></div>
    </div>
    <hr>
</div>
<div>&nbsp;</div>
<div class="sw-loader"></div>

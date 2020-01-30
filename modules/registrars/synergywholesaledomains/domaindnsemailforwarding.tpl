<script type="text/javascript">
    $(document).ready(EmailForwardPageReady({$domainid}));
</script>

<h3>Email forwarding</h3>
<p>Use the options below to create, manage and delete Email forwarding options set on the domain name.</p>

<input id="domainid" type="hidden" name="domainid" value="{$domainid}" />
<input id="domainname" type="hidden" name="domainname" value="{$domain}" />

<div class="row no-margin">
    <h3 class="inline">Email Forwards</h3>
    <button type="button" class="btn btn-success insertRow pull-right"><span class="glyphicon glyphicon-plus"></span></button>
</div>
<div class="container col-lg-12 row-table" id="emailforwards">
    <div class="row" id="divHeading">
        <div class="col-lg-4">Prefix</div>
        <div class="col-lg-2"></div>
        <div class="col-lg-4">Forward To</div>
        <div class="col-lg-2 "></div>
    </div>
    <hr>
</div>
<div>&nbsp;</div>
<div class="loader"></div>

<script type="text/javascript">
    $(document).ready(DnsUrlPageReady({$domainid}));
</script>

<h3>DNS records / URL forwards</h3>
<p>Use the options below to create, manage and delete DNS records and URL forwarding options set on the domain name.</p>

<input id="domainid" type="hidden" name="domainid" value="{$domainid}" />

<div class="row no-margin">
    <h3 class="inline">DNS records</h3>
    <button type="button" class="btn btn-success insertRow pull-right" data-append="dnsrecords">
        <span class="glyphicon glyphicon-plus"></span>
    </button>
</div>
<div class="container col-lg-12 row-table" id="dnsrecords">
    <div class="row no-margin" id="divHeading">
        <div class="col-lg-3">Host Name</div>
        <div class="col-lg-3">Address / Content</div>
        <div class="col-lg-2">Type</div>
        <div class="col-lg-1">TTL</div>
        <div class="col-lg-1">Priority</div>
        <div class="col-lg-2"></div>
    </div>
    <hr>
</div>
<div>&nbsp;</div>
<div class="loader"></div>

<div class="row no-margin">
    <h3 class="inline">URL forwards</h3>
    <button type="button" class="btn btn-success insertRow pull-right" data-append="urlforwards">
        <span class="glyphicon glyphicon-plus"></span>
    </button>
</div>
<div class="container col-lg-12 row-table" id="urlforwards">
    <div class="row no-margin" id="divHeading">
        <div class="col-lg-3">Host Name</div>
        <div class="col-lg-3">Address</div>
        <div class="col-lg-3">Type</div>
        <div class="col-lg-3"></div>
    </div>
    <hr>
</div>
<div>&nbsp;</div>
<div class="loader"></div>

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
    {if $records|@count eq 0}
    <div class="alert alert-info text-center">
        There are no DNSSEC security records configured on the domain name.
    </div>
    {/if}
    {if $records|@count gt 0}
        <h2>List DNSSEC DS Records</h2>
        <p>The following DNSSEC security records exist on your domain name. To prevent your domain name becoming unavailable on the internet please ensure the records below are correct and valid.
            <div class="table-responsive">
                <table class="table table-hover table-condensed table-striped" style="width: 95%">
                    <thread>
                        <tr>
                            <th>Key Tag</th>
                            <th>Algorithm</th>
                            <th>Digest</th>
                            <th>Digest Type</th>
                            <th>Action</th>
                        </tr>
                    </thread>
                    <tbody>
                        {foreach $records as $record}
                        <tr>
                            {foreach $record as $key => $value}
                            {if $key eq "keyTag"} <td><div class="sw-ellipis">{$value}</div></td>{/if}
                            {if $key eq "algorithm"} <td><div class="sw-ellipis">{$value}</div></td>{/if}
                            {if $key eq "digest"} <td><div class="sw-ellipis">{$value}</div></td>{/if}
                            {if $key eq "digestType"} <td><div class="sw-ellipis">{$value}</div></td>{/if}
                            {if $key eq "UUID"} {$uuid = $value}{/if}
                            {/foreach}
                            <td>
                                <form class="form-horizontal" role="form" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=manageDNSSEC">
                                    <input type="hidden" name="sub" value="delete"/>
                                    <input type="hidden" name="uuid" value="{$uuid}"/>
                                    <div class="form-group">
                                        <div class="col-sm-10">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </div>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
    {/if}
    <h2>Add DNSSEC DS Record</h2>
    <p>DNSSEC is a set of security extensions to DNS that provides the means for authenticating DNS records. Use the options below to create new DNSSEC DS records. Please ensure that the information you supply below is correct as invalid data will result in your domain name becoming unavailable on the internet.</p></br>
    <form class="form-horizontal" id="form" role="form" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=manageDNSSEC">
        <input type="hidden" name="sub" value="save" />
        <div class="form-group">
            <label class="control-label col-sm-2" for="keyTag">Key Tag:</label>
            <div class="col-xs-5">
                <input name="keyTag" type="text" class="form-control" id="keyTag" placeholder="Enter Key Tag">
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-sm-2" for="algorithm">Algorithm:</label>
            <div class="col-xs-5">
                <select name="algorithm" class="form-control" id="algorithm" form="form">
                    <optgroup label="DNSSEC Algorithms"></optgroup>
                    <option value="1">[1] RSA/MD5</option>
                    <option value="2">[2] Diffie-Hellman</option>
                    <option value="3">[3] DSA/SHA-1</option>
                    <option value="5">[5] RSA/SHA-1</option>
                    <option value="6">[6] DSA-NSEC3-SHA1</option>
                    <option value="7">[7] RSASHA1-NSEC3-SHA</option>
                    <option value="8">[8] RSA/SHA-256</option>
                    <option value="10">[10] RSA/SHA-512</option>
                    <option value="12">[12] GOST R 34.10-2001</option>
                    <option value="13">[13] ECDSA Curve P-256 with SHA-256</option>
                    <option value="14">[14] ECDSA Curve P-384 with SHA-384</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-sm-2" for="digest">Digest</label>
            <div class="col-xs-5">
                <input name="digest" type="text" class="form-control" id="digest" placeholder="Enter Digest">
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-sm-2" for="digestType">Digest Type:</label>
            <div class="col-xs-5">
                <select name="digestType" class="form-control" id="digestType">
                    <optgroup label="DNSSEC Digest Types"></optgroup>
                    <option value="1">[1] SHA-1</option>
                    <option value="2">[2] SHA-256</option>
                    <option value="3">[3] GOST R34.11-94</option>
                    <option value="4">[4] SHA-384</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
                <button type="submit" class="btn btn-primary">Add DNSSEC Record</button>
            </div>
        </div>
    </form>
    {/if}
<div id="Utils_RecordBrowser__Filter">
	<div class="buttons">
		<input type="button" {if isset($dont_hide)}style="display: none;"{/if} {$show_filters.attrs} value="{$show_filters.label}">
		<input type="button" {if !isset($dont_hide)}style="display: none;"{/if} {$hide_filters.attrs} value="{$hide_filters.label}">
	</div>
</div>

            </td>
        </tr>
        <tr>
            <td colspan="3" class="filters">

{$form_open}

<div id="recordbrowser_filters_{$id}" class="Utils_RecordBrowser__Filter" {if !isset($dont_hide)}style="display: none;"{/if}>
	<table border="0" cellpadding="0" cellspacing="0">
		<tr>
			{assign var=x value=0}
			{assign var=first value=1}
			{foreach item=f from=$filters}
				{if $x==4}
					{if $first==1}
						<td class="buttons">{$form_data.submit.html}</td>
						{assign var=first value=0}
					{else}
						<td />
					{/if}
					{assign var=x value=0}
					</tr>
					<tr>
				{/if}
				<td class="label">{$form_data.$f.label}</td>
				<td class="data">{$form_data.$f.html}</td>
				{assign var=x value=$x+1}
			{/foreach}
			{if $first==1}
				<td class="buttons">{$form_data.submit.html}</td>
			{/if}
		</tr>
	</table>
</div>

{$form_close}
            </td>
    	</tr>
	</tbody>
</table>
</div>

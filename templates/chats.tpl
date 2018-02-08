{include file="../includes/appmenu.tpl"}

<h1>Chats pendientes</h1>

<table width="100%" cellpadding="3">
	{foreach item=item from=$chats}
		<tr>
			{assign var="color" value="black"}
			{if $item->profile->gender eq "M"}{assign var="color" value="#4863A0"}{/if}
			{if $item->profile->gender eq "F"}{assign var="color" value="#F778A1"}{/if}

			<td><small>
				<!--PICTURE-->
				{if {$APRETASTE_ENVIRONMENT} eq "web"}
					<img class="profile-small" src="{$item->picture}" title="@{$item->profile->username}" alt="@{$item->profile->username}"/>
				{/if}

				<!--FLAG AND COUNTRY-->
				{if {$APRETASTE_ENVIRONMENT} eq "web" AND $item->profile->country}
					<img class="flag" src="/images/flags/{$item->profile->country|lower}.png" alt="{$item->profile->country}"/>
				{/if}

				<!--USERNAME-->
				{link href="PIZARRA PERFIL @{$item->profile->username}" caption="@{$item->profile->username}" style="color:{$color};"}

				<!--IS THE USER ONLINE-->
				{if {$item->profile->online}}&nbsp;<span class="online">ONLINE</span>{/if}<br/>

				<!--DATE-->
				{$item->last|date_format}
			</small></td>
			<td align="right">
				{button href="PIZARRA CHAT @{$item->profile->username}" caption="Charla" size="small" color="grey"}
			</td>
		</tr>
	{/foreach}
</table>

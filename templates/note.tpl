{include file="../layouts/appmenu.tpl"}

<table width="100%" cellspacing="0">
	<tr>
		<!--PICTURE -->
		{if {$APRETASTE_ENVIRONMENT} eq "web"}
			<td rowspan="3" width="50" align="left" valign="top">
				<img class="profile" src="{$note['picture']}" alt="@{$note['username']}"/>
			</td>
		{/if}

		<!--HEADER ROW -->
		<td style="font-size:small;" valign="top">
			{assign var="color" value="gray"}
			{if $note['gender'] eq "M"}{assign var="color" value="#4863A0"}{/if}
			{if $note['gender'] eq "F"}{assign var="color" value="#F778A1"}{/if}

			{if {$APRETASTE_ENVIRONMENT} eq "web"}
				<img class="flag" src="{$note['flag']}" alt="{$note['country']}"/>
			{/if}

			{link href="PIZARRA PERFIL @{$note['username']}" caption="@{$note['username']}" style="color:{$color};"}
			&middot;
			<small style="color:gray;">{$note['location']}</small>
			&middot;
			<small style="color:gray;">{$note['inserted']|date_format:"%b %e, %I:%M %p"|capitalize}</small>
		</td>
	</tr>
	<tr>
		<!--TEXT -->
		<td valign="middle" style="padding:10px 0px;">
			<big>{$note['text']|replace_url}</big>
			{space5}
			<small>
				{foreach from=$note['topics'] item=topic}
					{link href="PIZARRA {$topic['name']}" caption="#{$topic['name']} ({$topic['count']})" style="color:gray;"}&nbsp;
				{/foreach}
			</small>
		</td>
	</tr>
	<tr>
		<!--ACTION BUTTONS -->
		<td valign="bottom">
			<span class="emoji">
				<big>{link href="PIZARRA LIKE {$note['id']}" caption="&#128077;" wait="false" style="text-decoration:none; color:{$note['likecolor']};"}</big>
				<small>{$note['likes']}</small>
			<span>&nbsp;&nbsp;

			<span class="emoji">
				<big>{link href="PIZARRA UNLIKE {$note['id']}" caption="&#x1F44E;" wait="false" style="text-decoration:none; color:{$note['unlikecolor']};"}</big>
				<small>{$note['unlikes']}</small>
			</span>
		</td>
	</tr>
</table>

{if $note['comments']}

<hr style="margin:30px 0px"/>

{foreach from=$note['comments'] item=comment}
<table width="100%" cellspacing="0" bgcolor="#F2F2F2">
	<tr>
		<!--PICTURE -->
		{if {$APRETASTE_ENVIRONMENT} eq "web"}
			<td rowspan="3" width="50" valign="top">
				<img class="profile-comment" src="{$comment['picture']}" alt="@{$comment['username']}"/>
			</td>
		{/if}

		<!--HEADER ROW -->
		<td style="font-size:small;" valign="top">
			{assign var="color" value="gray"}
			{if $comment['gender'] eq "M"}{assign var="color" value="#4863A0"}{/if}
			{if $comment['gender'] eq "F"}{assign var="color" value="#F778A1"}{/if}

			{if {$APRETASTE_ENVIRONMENT} eq "web"}
				<img class="flag" src="{$note['flag']}" alt="{$note['country']}"/>
			{/if}

			{link href="PIZARRA PERFIL @{$comment['username']}" caption="@{$comment['username']}" style="color:{$color};"}
			&middot;
			<small style="color:gray;">{$comment['location']}</small>
			&middot;
			<small style="color:gray;">{$comment['inserted']|date_format:"%b %e, %I:%M %p"|capitalize}</small>
		</td>
	</tr>

	<!--TEXT -->
	<tr>
		<td valign="bottom">
			<big>{$comment['text']}</big>
		</td>
	</tr>
</table>
{space10}
{/foreach}

{/if}

{space15}

<center>
	{button href="PIZARRA COMENTAR {$note['id']} " caption="Comentar" popup="true" wait="false" desc="Escriba una comentario a esta nota"}
</center>

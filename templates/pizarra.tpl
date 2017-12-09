{if $isProfileIncomplete}
<table width="100%">
	<tr>
		<td align="center" bgcolor="#F6CED8">
			<p><small>Para usar pizarra al m&aacute;ximo, {if $notFromApp}{link href="PERFIL EDITAR" caption="complete su perfil"}{else}complete su perfil{/if}.</small></p>
		</td>
	</tr>
</table>
{space5}
{/if}

<table width="100%">
	<tr>
		<td><h1>Notas en la Pizarra</h1></td>
		<td align="right" valign="top">
			<nobr>
			{button href="PIZARRA" desc="Escriba una nota" caption="&#10010; Escribir" size="small" popup="true" wait="false"}
			{button href="PIZARRA BUSCAR" caption="Buscar" size="small" color="grey" popup="true" desc="Escriba un texto, @username o #hashtag a buscar"}
			</nobr>
		</td>
	</tr>
</table>

{foreach from=$notes item=note}
	{assign var="bgcolor" value="white"}
	{if $note@iteration is even}{assign var="bgcolor" value="#F2F2F2"}{/if}

	<table width="100%" cellspacing="0" bgcolor="{$bgcolor}" border=0>
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

				<font>
					{link href="PERFIL @{$note['username']}" caption="@{$note['username']}" style="color:{$color};"}
					&middot;
					<small style="color:gray;">{$note['location']}</small>
				</font>
			</td>
		</tr>
		<tr>
			<!--TEXT -->
			<td valign="middle" style="padding:10px 0px;">
				<big>{$note['text']|replace_url}</big>
				{space5}
				<small style="color:gray;">{$note['inserted']|date_format:"%B %e, %I:%M %p"|capitalize}</small>
			</td>
		</tr>
		<tr>
			<!--ACTION BUTTONS -->
			<td valign="bottom">
				<span class="emoji">
					<big>{link href="PIZARRA LIKE {$note['id']}" caption="&#128077;" wait="false" style="text-decoration:none; color:black;"}</big>
					<small>{$note['likes']}</small>
				<span>&nbsp;&nbsp;

				<span class="emoji">
					<big>{link href="PIZARRA UNLIKE {$note['id']}" caption="&#x1F44E;" wait="false" style="text-decoration:none; color:black;"}</big>
					<small>{$note['unlikes']}</small>
				</span>&nbsp;&nbsp;

				<span class="emoji">
					{link href="PIZARRA NOTA {$note['id']}" caption="&#128172;" style="text-decoration:none; color:black;"}
					<small>{$note['comments']}</small>
				</span>
			</td>
		</tr>
	</table>
	{space10}
{/foreach}

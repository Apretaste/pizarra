<table width="100%">
	<tr>
		<td><h1>{$header}</h1></td>
	</tr>
</table>

<table width="100%">
{foreach from=$notes item=note}
	<tr {if $note@iteration is even}bgcolor="#F2F2F2"{/if}>
		<td>
			{space5}
			<font color="gray">
				<small>
					{link href="PERFIL @{$note['name']}" caption="@{$note['name']}"},
					{if $note['gender'] eq "M"}<font color="#4863A0">M</font>,{/if}
					{if $note['gender'] eq "F"}<font color=#F778A1>F</font>,{/if}
					{if $note['picture'] eq 1}[foto],{/if}
					{$note['location']},
					<font color="red">{$note['likes']}&hearts;</font>
					{separator}
					{$note['inserted']|date_format:"%e/%m %l:%M %p"}
				</small>
			</font>
			<br/>
			<big><big>{$note['text']|replace_url}</big></big>
			{space5}
		</td>
	</tr>
{/foreach}
</table>

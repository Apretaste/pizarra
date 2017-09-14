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
		<td><h1>&Uacute;ltimas 50 notas</h1></td>
		<td align="right" valign="top">
			<nobr>
			{button href="PIZARRA" desc="Escriba una nota" caption="&#10010; Escribir" size="small" popup="true" wait="false"}
			{button href="PIZARRA BUSCAR" caption="Buscar" size="small" color="grey" popup="true" desc="Escriba un texto, @username o #hashtag a buscar"}
			</nobr>
		</td>
	</tr>
</table>

<table width="100%">
{foreach from=$notes item=note}
	<tr {if $note['ad']}bgcolor="#FFFF99"{elseif $note@iteration is even}bgcolor="#F2F2F2"{/if}>
		<td>
			{space5}
			<font color="gray">
				<small>
					{link href="PERFIL @{$note['username']}" caption="@{$note['username']}"},
					{$note['location']},
					{if $note['gender'] eq "M"}<font color="#4863A0">M</font>{/if}
					{if $note['gender'] eq "F"}<font color=#F778A1>F</font>{/if}
					{if $note['picture']}[foto]{/if}
					{separator}
					<font color="gray">{$note['inserted']|date_format:"%e/%m %l:%M %p"}</font>
					{separator}
					{link href="PIZARRA BLOQUEAR @{$note['username']}" caption="&#10006; Quitar" desc="Envie este email para bloquear a @{$note['username']} en tu Pizarra." wait="false"}
				</small>
			</font>
			<br/>
			<big><big>{$note['text']|replace_url}</big></big>
			<br/>
			<small>
				<font color="green">+</font>&nbsp;{link href="PIZARRA LIKE {$note['id']}" caption="Bueno" desc="Envie este email tal como esta para expresar gusto por este post de este usuario" wait="false"}
				(<font>{$note['likes']}</font>)
				{separator}
				<font color="red">-</font>&nbsp;{link href="PIZARRA UNLIKE {$note['id']}" caption="Malo" desc="Envie este email tal como esta para expresar que este post no le gusta" wait="false"}
				(<font>{$note['unlikes']}</font>)
				{separator}
				{link href="PIZARRA {$note['id']}* " caption="Comentar" desc="Escriba un comentario a esta nota" popup="true" wait="false"}
				{if $note['comments'] > 0}
					{link href="PIZARRA NOTA {$note['id']}" caption="({$note['comments']})"}
				{else}
					(0)
				{/if}
			</small>
			{space5}
		</td>
	</tr>
{/foreach}
</table>

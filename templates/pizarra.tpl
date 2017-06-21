{if $isProfileIncomplete}
<table width="100%">
	<tr>
		<td align="center" bgcolor="#F6CED8">
			<p><small>Para usar pizarra al m&aacute;ximo, {link href="PERFIL EDITAR" caption="complete su perfil"}.</small></p>
		</td>
	</tr>
</table>
{space5}
{/if}

<table width="100%">
	<tr>
		<td>
			<h1>&Uacute;ltimas 50 notas</h1>
			Usted tiene: <font color="red">{$likes} &hearts;</font> {separator} <font color="orange">{$follows} &#8619;</font> {separator} {$blocks} &#10006;
		</td>
		<td align="right" valign="top">
			{button href="PIZARRA reemplace este texto por su nota" body="Escriba una nota que no exeda los 130 caracteres en el asunto y envie este email" caption="&#10010; Escribir" size="small"}
			{button href="PIZARRA BUSCAR reemplace esto por un texto, @username o #hashtag a buscar" body='Escriba un texto a buscar, un @username o un #hashtag en el asunto, despues de la palabra BUSCAR, y envie este email. Por ejemplo: "PIZARRA BUSCAR amistad", "PIZARRA BUSCAR @apretaste" o "PIZARRA BUSCAR #cuba"' caption="Buscar"  size="small" color="grey"}
		</td>
	</tr>
</table>

{space5}

<table width="100%">
{foreach from=$notes item=note}
	<tr {if $note@iteration is even}bgcolor="#F2F2F2"{/if}>
		<td>
			{space5}
			<font color="gray">
				<small>
					<font color="orange">{if $note['friend']}&#8619;{/if}</font>
					{link href="PERFIL @{$note['username']}" caption="@{$note['username']}"},
					{$note['location']},
					{if $note['gender'] eq "M"}<font color="#4863A0">M</font>{/if}
					{if $note['gender'] eq "F"}<font color=#F778A1>F</font>{/if}
					{if $note['picture']}[foto]{/if}
					{separator}
					<font color="gray">{$note['inserted']|date_format:"%e/%m %l:%M %p"}</font>
				</small>
			</font>
			<br/>
			<big><big>{$note['text']|replace_url}</big></big>
			<br/>
			<small>
				{link href="PIZARRA LIKE {$note['id']}" caption="&hearts; Like" body="Envie este email tal como esta para expresar gusto por este post de este usuario"}
				[<font color="red">{$note['likes']}&hearts;</font>]
				{separator}
				{link href="NOTA @{$note['username']} Reemplace este texto por su nota" caption="&#x2605; Chat" body="Escriba en el asunto la nota que le llegara a @{$note['username']} y envie este email."}
				{separator}
				{link href="PIZARRA SEGUIR @{$note['username']}" caption="{if $note['friend']}&#10006; Parar{else}&#8619; Seguir{/if}" body="Siga a @{$note['username']} y vea sus notas arriba en la pizarra"}
				{separator}
				{link href="PIZARRA BLOQUEAR @{$note['username']}" caption="&#10006; Quitar" body="Envie este email para bloquear a @{$note['username']} en tu Pizarra."}
			</small>
			{space5}
		</td>
	</tr>
{/foreach}
</table>

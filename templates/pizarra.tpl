{if $isProfileIncomplete}
<table width="100%">
	<tr>
		<td align="center" bgcolor="#F6CED8">
			<p><small>Para usar pizarra al m&aacute;ximo, {link href="PERFIL EDITAR" caption="complete su perfil"}.</small></p>
		</td>
	</tr>
</table>
{/if}

<table width="100%">
	<tr>
		<td><h1>&Uacute;ltimas 50 notas</h1></td>
		<td align="right" valign="top">
			{button href="PIZARRA reemplace este texto por su nota" body="Escriba una nota que no exeda los 130 caracteres en el asunto y envie este email" caption="&#10010; Nueva nota"}
		</td>
	</tr>
</table>

<table width="100%">
{foreach from=$notes item=note}
	<tr {if $note@iteration is even}bgcolor="#F2F2F2"{/if}>
		<td>
			{space5}
			<font color="gray">
				<small>
					<font color="orange">{if $note['friend']}&#8619;{/if}</font>
					{link href="PERFIL @{$note['name']}" caption="@{$note['name']}"},
					{$note['location']},
					{if $note['gender'] eq "M"}<font color="#4863A0">M</font>{/if}
					{if $note['gender'] eq "F"}<font color=#F778A1>F</font>{/if}
					{if $note['picture'] eq 1}[foto]{/if}
					{separator}
					<font color="gray">{$note['inserted']|date_format:"%e/%m %I:%M %p"}</font>
				</small>
			</font>
			<br/>
			<big><big>{$note['text']|replace_url}</big></big>
			<br/>
			<small>
				{if $username neq $note['name']}
					{link href="PIZARRA LIKE {$note['id']}" caption="&hearts; Like" body="Envie este email tal como esta para expresar gusto por este post de este usuario"}
					[<font color="red">{$note['likes']}&hearts;</font>]
					{separator}
					{link href="NOTA @{$note['name']} Reemplace este texto por su nota" caption="&#x2605; Chat" body="Escriba en el asunto la nota que le llegara a @{$note['name']} y envie este email."}
					{separator}
					{link href="PIZARRA SEGUIR @{$note['name']}" caption="{if $note['friend']}&#10006; Parar{else}&#8619; Seguir{/if}" body="Siga a @{$note['name']} y vea sus notas arriba en la pizarra"}
					{separator}
					{link href="PIZARRA BLOQUEAR @{$note['name']}" caption="&#10006; Quitar" body="Envie este email para bloquear a @{$note['name']} en tu Pizarra."}
				{else}
					<font color="red">Tienes {$note['likes']} &hearts;</font>
				{/if}
			</small>
			{space5}
		</td>
	</tr>
{/foreach}
</table>

{space30}

<center>
	<p><small>&iquest;Extra&ntilde;as a tus amigos? {link href="INVITAR su@amigo.cu" caption="Inv&iacute;talos" body="Cambie en el asunto su@amigo.cu por el email de la persona a invitar. Puede agregar varios emails, separados por espacios o comas"} y gana tickets para {link href="RIFA" caption="nuestra rifa"}.</small></p>
</center>

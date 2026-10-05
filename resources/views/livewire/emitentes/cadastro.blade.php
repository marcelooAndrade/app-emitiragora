<div class="grid gap-6">

    <x-ui.page-header
        eyebrow="Configuração"
        title="Emitente"
        description="Dados cadastrais de quem assina o CT-e e o MDF-e. Ambiente, série e certificado ficam nas telas próprias." />

    @if (session('sucesso'))
        <x-ui.alert variant="success">{{ session('sucesso') }}</x-ui.alert>
    @endif

    <form wire:submit="salvar" class="grid gap-6">
        <x-ui.card title="Identificação" subtitle="CNPJ {{ $this->cnpjFormatado() }}">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.field label="Razão social" for="em-razao" required :error="$errors->first('form.razao_social')" class="lg:col-span-2">
                    <x-ui.input id="em-razao" wire:model="form.razao_social" maxlength="200" />
                </x-ui.field>
                <x-ui.field label="Nome fantasia" for="em-fantasia" :error="$errors->first('form.nome_fantasia')">
                    <x-ui.input id="em-fantasia" wire:model="form.nome_fantasia" maxlength="200" />
                </x-ui.field>
                <x-ui.field label="Inscrição estadual" for="em-ie" required :error="$errors->first('form.inscricao_estadual')" hint="ISENTO quando não houver.">
                    <x-ui.input id="em-ie" wire:model="form.inscricao_estadual" maxlength="20" />
                </x-ui.field>
                <x-ui.field label="Inscrição municipal" for="em-im" :error="$errors->first('form.inscricao_municipal')" hint="Obrigatória para emitir NFS-e.">
                    <x-ui.input id="em-im" wire:model="form.inscricao_municipal" maxlength="20" />
                </x-ui.field>
                <x-ui.field label="CRT" for="em-crt" required :error="$errors->first('form.crt')">
                    <x-ui.select id="em-crt" wire:model="form.crt">
                        <option value="1">1. Simples Nacional</option>
                        <option value="2">2. Simples Nacional, excesso de sublimite</option>
                        <option value="3">3. Regime Normal</option>
                        <option value="4">4. MEI</option>
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="CNAE" for="em-cnae" :error="$errors->first('form.cnae')">
                    <x-ui.input id="em-cnae" wire:model="form.cnae" maxlength="7" numeric />
                </x-ui.field>
                <x-ui.field label="Telefone" for="em-telefone" :error="$errors->first('form.telefone')">
                    <x-ui.input id="em-telefone" wire:model="form.telefone" maxlength="20" />
                </x-ui.field>
                <x-ui.field label="E-mail" for="em-email" :error="$errors->first('form.email')">
                    <x-ui.input id="em-email" type="email" wire:model="form.email" maxlength="254" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Endereço" subtitle="Pesquise o CEP para preencher município, UF e código IBGE.">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.field label="CEP" for="em-cep" :error="$errors->first('form.cep')">
                    <div class="flex gap-2">
                        <x-ui.input id="em-cep" wire:model="form.cep" numeric class="flex-1" />
                        <x-ui.button variant="secondary" size="md" wire:click="buscarCep" wire:loading.attr="disabled" wire:target="buscarCep">
                            <span wire:loading.remove wire:target="buscarCep">CEP</span>
                            <span wire:loading wire:target="buscarCep">...</span>
                        </x-ui.button>
                    </div>
                </x-ui.field>
                <x-ui.field label="Logradouro" for="em-logradouro" :error="$errors->first('form.logradouro')" class="lg:col-span-2">
                    <x-ui.input id="em-logradouro" wire:model="form.logradouro" maxlength="255" />
                </x-ui.field>
                <x-ui.field label="Número" for="em-numero" :error="$errors->first('form.numero')">
                    <x-ui.input id="em-numero" wire:model="form.numero" maxlength="60" />
                </x-ui.field>
                <x-ui.field label="Complemento" for="em-complemento" :error="$errors->first('form.complemento')">
                    <x-ui.input id="em-complemento" wire:model="form.complemento" maxlength="255" />
                </x-ui.field>
                <x-ui.field label="Bairro" for="em-bairro" :error="$errors->first('form.bairro')">
                    <x-ui.input id="em-bairro" wire:model="form.bairro" maxlength="255" />
                </x-ui.field>
                <x-ui.field label="Município" for="em-municipio" :error="$errors->first('form.municipio')">
                    <x-ui.input id="em-municipio" wire:model="form.municipio" maxlength="255" />
                </x-ui.field>
                <x-ui.field label="UF" for="em-uf" :error="$errors->first('form.uf')">
                    <x-ui.input id="em-uf" wire:model="form.uf" maxlength="2" />
                </x-ui.field>
                <x-ui.field label="Código IBGE" for="em-ibge" :error="$errors->first('form.codigo_municipio')" hint="Araras é 3503307.">
                    <x-ui.input id="em-ibge" wire:model="form.codigo_municipio" maxlength="7" numeric />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Cobrança Pix" subtitle="Com a chave preenchida, cada parcela lançada em contas a receber sai com código de pagamento.">
            <x-ui.field label="Chave Pix" for="em-pix" class="sm:max-w-md" :error="$errors->first('form.chave_pix')"
                hint="CNPJ, e-mail, telefone ou chave aleatória. O recebedor sai do nome fantasia e do município.">
                <x-ui.input id="em-pix" wire:model="form.chave_pix" maxlength="77" placeholder="Deixe vazio para desligar" />
            </x-ui.field>
        </x-ui.card>

        <div>
            <x-ui.button type="submit">Salvar emitente</x-ui.button>
        </div>
    </form>
</div>

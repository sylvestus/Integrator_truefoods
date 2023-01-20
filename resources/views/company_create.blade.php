@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Create Company</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('company_master.store') }}">
                            @csrf
                            <div class="card mb-2">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="company_name" class="col-form-label">Company
                                                    Name</label>
                                                <input id="company_name" type="text" class="form-control"
                                                       name="company_name" required autocomplete="company_name"
                                                       autofocus>
                                                @error('company_name')
                                                <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                         </span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="company_email" class="col-form-label">Company
                                                    Email</label>
                                                <input id="company_email" type="email" class="form-control"
                                                       name="company_email" required autocomplete="company_email"
                                                       autofocus>
                                                @error('company_email')
                                                <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                         </span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="account_number" class="col-form-label">Netsuite&nbsp;Account&nbsp;Number</label>
                                                <input id="account_number" type="text" class="form-control"
                                                       name="account_number" required autocomplete="account_number"
                                                       autofocus>
                                                @error('account_number')
                                                <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                         </span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="consumerKey" class="col-form-label">Consumer&nbsp;Key</label>
                                                <input id="consumerKey" type="text" class="form-control"
                                                       name="consumerKey">
                                                @error('consumerKey')
                                                <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                         </span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="tokenId" class="col-form-label">Token&nbsp;Id</label>
                                                <input id="tokenId" type="text" class="form-control"
                                                       name="tokenId">
                                                @error('tokenId')
                                                <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                         </span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="consumerSecret" class="col-form-label">Consumer&nbsp;Secret</label>
                                                <input id="consumerSecret" type="text" class="form-control"
                                                       name="consumerSecret">
                                                @error('consumerSecret')
                                                <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                         </span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="tokenSecret" class="col-form-label">Token&nbsp;Secret</label>
                                                <input id="tokenSecret" type="text" class="form-control"
                                                       name="tokenSecret">
                                                @error('tokenSecret')
                                                <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                         </span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="staging_consumerKey" class=" col-form-label">Staging&nbsp;Consumer&nbsp;Key</label>
                                                <input id="staging_consumerKey" type="text" class="form-control"
                                                       name="staging_consumerKey">
                                                @error('staging_consumerKey')
                                                <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                         </span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="staging_tokenId" class="col-form-label">Staging&nbsp;Token&nbsp;Id
                                                    </label>
                                                <input id="staging_tokenId" type="text" class="form-control"
                                                       name="staging_tokenId">
                                                @error('staging_tokenId')
                                                <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                         </span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="staging_consumerSecret" class="col-form-label">Staging&nbsp;Consumer&nbsp;Secret
                                                    </label>
                                                <input id="staging_consumerSecret" type="text" class="form-control"
                                                       name="staging_consumerSecret">
                                                @error('staging_consumerSecret')
                                                <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                         </span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="staging_tokenSecret" class="col-form-label">Staging&nbsp;Token&nbsp;Secret
                                                </label>
                                                <input id="staging_tokenSecret" type="text" class="form-control"
                                                       name="staging_tokenSecret">
                                                @error('staging_tokenSecret')
                                                <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                         </span>
                                                @enderror
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <div class="row mb-0">
                                <div class="col-md-6">

                                </div>
                                <div class="col-md-6"  >
                                    <button style="float: right" type="submit" class="btn btn-primary">
                                        Save
                                    </button>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

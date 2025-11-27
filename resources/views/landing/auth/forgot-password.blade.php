@extends('layouts.auth')

@section('title', 'Lupa Password - XpressPOS')

@section('content')
<div>
    <h2 class="text-center text-2xl font-bold text-gray-900 mb-2">
        Lupa Password?
    </h2>
    <p class="text-center text-sm text-gray-600 mb-8">
        Masukkan email Anda dan kami akan mengirimkan link untuk reset password
    </p>
</div>

<form class="space-y-6" action="#" method="POST">
    @csrf
    
    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
            Email
        </label>
        <input id="email" name="email" type="email" autocomplete="email" required 
               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-500" 
               placeholder="nama@email.com" value="{{ old('email') }}">
    </div>

    <div>
        <button type="submit" 
                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-lg">
            Kirim Link Reset Password
        </button>
    </div>

    <div class="text-center">
        <a href="{{ route('landing.login') }}" class="text-sm text-blue-600 hover:text-blue-500 transition-colors duration-300">
            ← Kembali ke halaman masuk
        </a>
    </div>
</form>
@endsection
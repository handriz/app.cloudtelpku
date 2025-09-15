<x-app-layout>
    @include('admin.users.partials.edit_content', [
        'user' => $user,
        'roles' => $roles,
        'hierarchyLevels' => $hierarchyLevels
    ])
</x-app-layout>
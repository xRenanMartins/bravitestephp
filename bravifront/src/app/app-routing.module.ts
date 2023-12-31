import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

const routes: Routes = [
  {
    path: 'persons',
    loadChildren: () =>
      import('./modules/person/person.module').then(
        (m) => m.PersonModule
      ),
    data: { title: 'Lista de Contatos' },
  },
];

@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule]
})
export class AppRoutingModule { }

import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PersonComponent } from './pages/person/person.component';

const routes: Routes = [
  {
    path: '',
    component: PersonComponent,
    data: { title: 'Pessoas' }
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class PersonRoutingModule { }